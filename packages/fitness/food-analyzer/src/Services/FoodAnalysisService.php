<?php

namespace Fitness\FoodAnalyzer\Services;

use App\Models\User;
use Fitness\FoodAnalyzer\Drivers\Contracts\FoodImageAnalyzer;
use Fitness\FoodAnalyzer\Drivers\FoodAnalysisResult;
use Fitness\FoodAnalyzer\Drivers\FoodImage;
use Fitness\FoodAnalyzer\Exceptions\AnalysisFailedException;
use Fitness\FoodAnalyzer\Exceptions\DailyLimitExceededException;
use Fitness\FoodAnalyzer\Exceptions\DriverNotAvailableException;
use Fitness\FoodAnalyzer\Exceptions\FoodAnalyzerException;
use Fitness\FoodAnalyzer\Exceptions\NoFoodDetectedException;
use Fitness\FoodAnalyzer\Models\FoodAnalysis;
use Fitness\FoodAnalyzer\Repositories\Contracts\FoodAnalysisRepository;
use Fitness\LlmLog\Models\LlmLog;
use Fitness\LlmLog\Repositories\Contracts\LlmLogRepository;
use Fitness\Profile\Services\UserCalendar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * 照片分析流程：檢查每日上限（使用者時區切日）→ 呼叫 driver → 寫入 LLM 呼叫紀錄（llm_logs）與分析紀錄（food_analyses），
 * 成功或失敗都寫，不含照片。
 */
class FoodAnalysisService
{
    /**
     * 寫入 llm_logs.feature 的用途名稱。
     */
    const LLM_FEATURE = 'food_analysis';

    public function __construct(
        protected FoodAnalysisRepository $analyses,
        protected LlmLogRepository $llmLogs,
        protected FoodAnalyzerManager $manager,
    ) {}

    /**
     * 每人每天最多分析幾次。
     */
    public function dailyLimit(): int
    {
        return (int) config('fitness.food_analyzer.daily_limit');
    }

    /**
     * 使用者今天（使用者時區）還能分析幾次。
     */
    public function remainingToday(User $user): int
    {
        $from = UserCalendar::today($user);
        $used = $this->analyses->countBetween($user->id, $from, $from->addDay());

        return max(0, $this->dailyLimit() - $used);
    }

    /**
     * 取得使用者自己的一筆成功的分析（確認頁帶入用）；不是自己的或失敗的回傳 null。
     */
    public function findSucceeded(User $user, int $id): ?FoodAnalysis
    {
        return $this->analyses->findSucceededForUser($user->id, $id);
    }

    /**
     * 分析一張照片並寫入紀錄，回傳成功的分析紀錄（項目在 items()，LLM 呼叫細節在 llmLog）。
     * 超過上限或 driver 不可用時不呼叫 driver、不寫紀錄；driver 失敗時寫一筆失敗紀錄後丟出對應的例外。
     *
     * @throws DailyLimitExceededException
     * @throws DriverNotAvailableException
     * @throws FoodAnalyzerException
     */
    public function analyze(User $user, FoodImage $image, ?string $hint = null): FoodAnalysis
    {
        if ($this->remainingToday($user) <= 0) {
            throw new DailyLimitExceededException($this->dailyLimit());
        }

        $driver = $this->manager->get();
        $hint = $this->normalizeHint($hint);
        $prompt = $driver->prompt($hint);
        $startedAt = hrtime(true);

        try {
            $result = $driver->analyze($image, $hint);

            if ($result->items === []) {
                throw (new NoFoodDetectedException)->withResponse($result->rawResponse, $result->inputTokens, $result->outputTokens);
            }
        } catch (FoodAnalyzerException $e) {
            $this->recordFailure($user, $driver, $prompt, $hint, $e, $startedAt);

            throw $e;
        } catch (Throwable $e) {
            report($e);
            $failure = new AnalysisFailedException($e->getMessage(), previous: $e);
            $this->recordFailure($user, $driver, $prompt, $hint, $failure, $startedAt);

            throw $failure;
        }

        return $this->recordSuccess($user, $driver, $prompt, $hint, $result, $startedAt);
    }

    /**
     * 寫入成功紀錄：LLM 呼叫紀錄與分析紀錄，result 存正規化後的項目。
     */
    protected function recordSuccess(User $user, FoodImageAnalyzer $driver, string $prompt, ?string $hint, FoodAnalysisResult $result, int $startedAt): FoodAnalysis
    {
        $durationMs = $this->elapsedMs($startedAt);

        return DB::transaction(function () use ($user, $driver, $prompt, $hint, $result, $durationMs) {
            $log = $this->llmLogs->create($user->id, [
                ...$this->llmLogAttributes($driver, $prompt, $durationMs),
                'status' => LlmLog::STATUS_SUCCEEDED,
                'response' => $result->rawResponse,
                'input_tokens' => $result->inputTokens,
                'output_tokens' => $result->outputTokens,
            ]);

            return $this->analyses->create($user->id, [
                'llm_log_id' => $log->id,
                'status' => FoodAnalysis::STATUS_SUCCEEDED,
                'hint' => $hint,
                'result' => $result->itemsToArray(),
            ]);
        });
    }

    /**
     * 寫入失敗紀錄（算一次）：LLM 呼叫紀錄與分析紀錄都帶失敗種類，並在例外上填入 driver 名稱供文案使用。
     */
    protected function recordFailure(User $user, FoodImageAnalyzer $driver, string $prompt, ?string $hint, FoodAnalyzerException $e, int $startedAt): void
    {
        $durationMs = $this->elapsedMs($startedAt);
        $e->providerLabel = $driver->label();

        DB::transaction(function () use ($user, $driver, $prompt, $hint, $e, $durationMs) {
            $log = $this->llmLogs->create($user->id, [
                ...$this->llmLogAttributes($driver, $prompt, $durationMs),
                'status' => LlmLog::STATUS_FAILED,
                'error' => $e->errorCode(),
                'response' => $e->rawResponse ?? ['error_message' => Str::limit($e->getPrevious()?->getMessage() ?? $e->getMessage(), 1000)],
                'input_tokens' => $e->inputTokens,
                'output_tokens' => $e->outputTokens,
            ]);

            $this->analyses->create($user->id, [
                'llm_log_id' => $log->id,
                'status' => FoodAnalysis::STATUS_FAILED,
                'error' => $e->errorCode(),
                'hint' => $hint,
            ]);
        });
    }

    /**
     * LLM 呼叫紀錄成功與失敗共用的欄位：LLM 種類（driver 別名）、模型、用途、完整 prompt 與耗時。
     *
     * @return array<string, mixed>
     */
    protected function llmLogAttributes(FoodImageAnalyzer $driver, string $prompt, int $durationMs): array
    {
        return [
            'provider' => $this->manager->defaultDriver(),
            'model' => $driver->model(),
            'feature' => self::LLM_FEATURE,
            'content' => $prompt,
            'duration_ms' => $durationMs,
        ];
    }

    /**
     * 從 hrtime 起點到現在經過的毫秒數。
     */
    protected function elapsedMs(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }

    /**
     * 補充說明去頭尾空白、限制長度，空字串視為沒有填。
     */
    protected function normalizeHint(?string $hint): ?string
    {
        $hint = trim((string) $hint);

        return $hint === '' ? null : Str::substr($hint, 0, (int) config('fitness.food_analyzer.hint_max_length'));
    }
}
