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
use Fitness\Profile\Services\UserCalendar;
use Illuminate\Support\Str;
use Throwable;

/**
 * 照片分析流程：檢查每日上限（使用者時區切日）→ 呼叫 driver → 寫一筆紀錄（成功或失敗都寫，不含照片）。
 */
class FoodAnalysisService
{
    public function __construct(
        protected FoodAnalysisRepository $analyses,
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
     * 分析一張照片並寫入紀錄，回傳成功的紀錄（項目在 items()）。
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
        $startedAt = hrtime(true);

        try {
            $result = $driver->analyze($image, $hint);

            if ($result->items === []) {
                throw (new NoFoodDetectedException)->withResponse($result->rawResponse, $result->inputTokens, $result->outputTokens);
            }
        } catch (FoodAnalyzerException $e) {
            $this->recordFailure($user, $driver, $hint, $e, $startedAt);

            throw $e;
        } catch (Throwable $e) {
            report($e);
            $failure = new AnalysisFailedException($e->getMessage(), previous: $e);
            $this->recordFailure($user, $driver, $hint, $failure, $startedAt);

            throw $failure;
        }

        return $this->recordSuccess($user, $driver, $hint, $result, $startedAt);
    }

    /**
     * 寫入成功紀錄，result 存正規化後的項目。
     */
    protected function recordSuccess(User $user, FoodImageAnalyzer $driver, ?string $hint, FoodAnalysisResult $result, int $startedAt): FoodAnalysis
    {
        return $this->analyses->create($user->id, [
            ...$this->baseAttributes($driver, $hint, $startedAt),
            'status' => FoodAnalysis::STATUS_SUCCEEDED,
            'result' => $result->itemsToArray(),
            'raw_response' => $result->rawResponse,
            'input_tokens' => $result->inputTokens,
            'output_tokens' => $result->outputTokens,
        ]);
    }

    /**
     * 寫入失敗紀錄（算一次），並在例外上填入 driver 名稱供文案使用。
     */
    protected function recordFailure(User $user, FoodImageAnalyzer $driver, ?string $hint, FoodAnalyzerException $e, int $startedAt): void
    {
        $e->providerLabel = $driver->label();

        $this->analyses->create($user->id, [
            ...$this->baseAttributes($driver, $hint, $startedAt),
            'status' => FoodAnalysis::STATUS_FAILED,
            'error' => $e->errorCode(),
            'raw_response' => $e->rawResponse,
            'input_tokens' => $e->inputTokens,
            'output_tokens' => $e->outputTokens,
        ]);
    }

    /**
     * 成功與失敗共用的欄位：driver、模型、補充說明與耗時。
     *
     * @return array<string, mixed>
     */
    protected function baseAttributes(FoodImageAnalyzer $driver, ?string $hint, int $startedAt): array
    {
        return [
            'provider' => $this->manager->defaultDriver(),
            'model' => $driver->model(),
            'hint' => $hint,
            'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
        ];
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
