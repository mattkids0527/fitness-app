<?php

namespace Fitness\FoodAnalyzer\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Fitness\FoodAnalyzer\Drivers\FoodAnalysisResult;
use Fitness\FoodAnalyzer\Exceptions\AnalysisFailedException;
use Fitness\FoodAnalyzer\Exceptions\AnalysisTimeoutException;
use Fitness\FoodAnalyzer\Exceptions\DailyLimitExceededException;
use Fitness\FoodAnalyzer\Exceptions\DriverNotAvailableException;
use Fitness\FoodAnalyzer\Exceptions\FoodAnalyzerException;
use Fitness\FoodAnalyzer\Exceptions\NoFoodDetectedException;
use Fitness\FoodAnalyzer\Exceptions\ProviderRateLimitedException;
use Fitness\FoodAnalyzer\Models\FoodAnalysis;
use Fitness\FoodAnalyzer\Services\FoodAnalysisService;
use Fitness\FoodAnalyzer\Tests\Concerns\FakesFoodAnalyzer;
use Fitness\FoodAnalyzer\Tests\Fakes\FakeFoodAnalyzer;
use Fitness\LlmLog\Models\LlmLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * 照片分析服務：成功與失敗的紀錄、每日上限與切日、driver 不可用、補充說明整理、讀回自己的分析。
 */
class FoodAnalysisServiceTest extends TestCase
{
    use FakesFoodAnalyzer, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-21 12:40:00', 'Asia/Taipei'));
    }

    /**
     * 取得分析服務（要在 fakeFoodAnalyzer() 之後呼叫）。
     */
    protected function service(): FoodAnalysisService
    {
        return app(FoodAnalysisService::class);
    }

    /**
     * 執行一次分析並預期丟出例外，回傳該例外。
     */
    protected function analyzeExpectingFailure(User $user, ?string $hint = null): FoodAnalyzerException
    {
        try {
            $this->service()->analyze($user, $this->foodImage(), $hint);
        } catch (FoodAnalyzerException $e) {
            return $e;
        }

        $this->fail('Expected a FoodAnalyzerException.');
    }

    public function test_success_records_analysis_and_llm_log(): void
    {
        $fake = $this->fakeFoodAnalyzer();
        $user = User::factory()->create();

        $analysis = $this->service()->analyze($user, $this->foodImage(), '飯只吃一半');

        $this->assertCount(1, $fake->calls);
        $this->assertSame('飯只吃一半', $fake->calls[0]['hint']);

        $analysis = FoodAnalysis::with('llmLog')->sole();
        $this->assertSame($user->id, $analysis->user_id);
        $this->assertSame(FoodAnalysis::STATUS_SUCCEEDED, $analysis->status);
        $this->assertNull($analysis->error);
        $this->assertSame('飯只吃一半', $analysis->hint);
        // jsonb 會把 25.0 存成 25、重排鍵的順序，所以用 assertEquals 比對
        $this->assertEquals(FakeFoodAnalyzer::defaultResult()->itemsToArray(), $analysis->result);
        $this->assertCount(4, $analysis->items());

        $log = LlmLog::sole();
        $this->assertSame($log->id, $analysis->llm_log_id);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('fake', $log->provider);
        $this->assertSame('fake-model', $log->model);
        $this->assertSame(FoodAnalysisService::LLM_FEATURE, $log->feature);
        $this->assertSame("辨識照片中的食物。\n補充說明：飯只吃一半", $log->content);
        $this->assertSame(LlmLog::STATUS_SUCCEEDED, $log->status);
        $this->assertSame(1290, $log->input_tokens);
        $this->assertSame(320, $log->output_tokens);
        $this->assertIsInt($log->duration_ms);
        $this->assertArrayHasKey('items', $log->response);
    }

    public function test_driver_failures_are_recorded_and_rethrown(): void
    {
        $fake = $this->fakeFoodAnalyzer();
        $user = User::factory()->create();

        $failures = [
            'no_food' => (new NoFoodDetectedException)->withResponse(['is_food' => false, 'items' => []], 1200, 20),
            'rate_limited' => new ProviderRateLimitedException('429'),
            'timeout' => new AnalysisTimeoutException('cURL error 28'),
            'failed' => new AnalysisFailedException('bad response'),
        ];

        foreach ($failures as $code => $exception) {
            $fake->nextException = $exception;

            $thrown = $this->analyzeExpectingFailure($user);

            $this->assertSame($exception, $thrown);
            $this->assertSame('Fake', $thrown->providerLabel);

            $analysis = FoodAnalysis::latest('id')->first();
            $this->assertSame(FoodAnalysis::STATUS_FAILED, $analysis->status);
            $this->assertSame($code, $analysis->error);
            $this->assertNull($analysis->result);

            $log = LlmLog::find($analysis->llm_log_id);
            $this->assertSame(LlmLog::STATUS_FAILED, $log->status);
            $this->assertSame($code, $log->error);
        }

        $this->assertSame(4, FoodAnalysis::count());
        $this->assertEquals(['is_food' => false, 'items' => []], LlmLog::where('error', 'no_food')->sole()->response);
        $this->assertSame(1200, LlmLog::where('error', 'no_food')->sole()->input_tokens);
        $this->assertSame(16, $this->service()->remainingToday($user));
    }

    public function test_unexpected_exception_becomes_failed(): void
    {
        $fake = $this->fakeFoodAnalyzer();
        $fake->nextException = new RuntimeException('Something exploded');
        $user = User::factory()->create();

        $thrown = $this->analyzeExpectingFailure($user);

        $this->assertInstanceOf(AnalysisFailedException::class, $thrown);
        $this->assertInstanceOf(RuntimeException::class, $thrown->getPrevious());
        $this->assertSame('failed', FoodAnalysis::sole()->error);
        $this->assertSame(['error_message' => 'Something exploded'], LlmLog::sole()->response);
    }

    public function test_empty_items_means_no_food(): void
    {
        $fake = $this->fakeFoodAnalyzer();
        $fake->nextResult = new FoodAnalysisResult([], ['items' => []], 1100, 15);
        $user = User::factory()->create();

        $thrown = $this->analyzeExpectingFailure($user);

        $this->assertInstanceOf(NoFoodDetectedException::class, $thrown);
        $this->assertSame('no_food', FoodAnalysis::sole()->error);
        $this->assertSame(1100, LlmLog::sole()->input_tokens);
        $this->assertSame(15, LlmLog::sole()->output_tokens);
    }

    public function test_daily_limit_counts_failures_and_blocks_driver(): void
    {
        config(['fitness.food_analyzer.daily_limit' => 3]);
        $fake = $this->fakeFoodAnalyzer();
        $user = User::factory()->create();

        $this->service()->analyze($user, $this->foodImage());
        $fake->nextException = new ProviderRateLimitedException('429');
        $this->analyzeExpectingFailure($user);
        $this->analyzeExpectingFailure($user);
        $this->assertSame(0, $this->service()->remainingToday($user));

        $thrown = $this->analyzeExpectingFailure($user);

        $this->assertInstanceOf(DailyLimitExceededException::class, $thrown);
        $this->assertSame('daily_limit', $thrown->errorCode());
        $this->assertCount(3, $fake->calls);
        $this->assertSame(3, FoodAnalysis::count());
        $this->assertSame(3, LlmLog::count());
    }

    public function test_daily_limit_resets_at_user_midnight(): void
    {
        config(['fitness.food_analyzer.daily_limit' => 2]);
        $fake = $this->fakeFoodAnalyzer();
        $user = User::factory()->create();

        $this->travelTo(CarbonImmutable::parse('2026-11-20 23:59:00', 'Asia/Taipei'));
        $this->service()->analyze($user, $this->foodImage());
        $this->service()->analyze($user, $this->foodImage());
        $this->assertSame(0, $this->service()->remainingToday($user));

        $this->travelTo(CarbonImmutable::parse('2026-11-21 00:00:00', 'Asia/Taipei'));
        $this->assertSame(2, $this->service()->remainingToday($user));
        $this->service()->analyze($user, $this->foodImage());
        $this->assertSame(1, $this->service()->remainingToday($user));
        $this->assertCount(3, $fake->calls);
    }

    public function test_driver_unavailable_is_not_counted(): void
    {
        $user = User::factory()->create();

        config(['fitness.food_analyzer.driver' => 'missing']);
        $thrown = $this->analyzeExpectingFailure($user);
        $this->assertInstanceOf(DriverNotAvailableException::class, $thrown);
        $this->assertSame('unavailable', $thrown->errorCode());

        $fake = $this->fakeFoodAnalyzer(configured: false);
        $thrown = $this->analyzeExpectingFailure($user);
        $this->assertInstanceOf(DriverNotAvailableException::class, $thrown);

        $this->assertSame([], $fake->calls);
        $this->assertSame(0, FoodAnalysis::count());
        $this->assertSame(0, LlmLog::count());
        $this->assertSame(20, $this->service()->remainingToday($user));
    }

    public function test_hint_is_normalized(): void
    {
        $fake = $this->fakeFoodAnalyzer();
        $user = User::factory()->create();

        $this->service()->analyze($user, $this->foodImage(), "   \n ");
        $this->service()->analyze($user, $this->foodImage(), '  飯只吃一半  ');
        $this->service()->analyze($user, $this->foodImage(), str_repeat('湯', 210));

        $this->assertNull($fake->calls[0]['hint']);
        $this->assertSame('飯只吃一半', $fake->calls[1]['hint']);
        $this->assertSame(str_repeat('湯', 200), $fake->calls[2]['hint']);

        $hints = FoodAnalysis::orderBy('id')->pluck('hint')->all();
        $this->assertSame([null, '飯只吃一半', str_repeat('湯', 200)], $hints);
        $this->assertSame('辨識照片中的食物。', LlmLog::orderBy('id')->first()->content);
    }

    public function test_find_succeeded_only_returns_own_successful(): void
    {
        $fake = $this->fakeFoodAnalyzer();
        $user = User::factory()->create();
        $other = User::factory()->create();

        $mine = $this->service()->analyze($user, $this->foodImage());
        $theirs = $this->service()->analyze($other, $this->foodImage());
        $fake->nextException = new AnalysisTimeoutException('timeout');
        $this->analyzeExpectingFailure($user);
        $failed = FoodAnalysis::where('status', FoodAnalysis::STATUS_FAILED)->sole();

        $this->assertTrue($this->service()->findSucceeded($user, $mine->id)?->is($mine));
        $this->assertNull($this->service()->findSucceeded($user, $theirs->id));
        $this->assertNull($this->service()->findSucceeded($user, $failed->id));
        $this->assertNull($this->service()->findSucceeded($user, 999999));
    }
}
