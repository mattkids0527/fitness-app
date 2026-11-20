<?php

namespace Fitness\Meal\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Fitness\FoodAnalyzer\Exceptions\AnalysisTimeoutException;
use Fitness\FoodAnalyzer\Exceptions\NoFoodDetectedException;
use Fitness\FoodAnalyzer\Exceptions\ProviderRateLimitedException;
use Fitness\FoodAnalyzer\Models\FoodAnalysis;
use Fitness\FoodAnalyzer\Services\FoodAnalysisService;
use Fitness\FoodAnalyzer\Tests\Concerns\FakesFoodAnalyzer;
use Fitness\FoodAnalyzer\Tests\Fakes\FakeFoodAnalyzer;
use Fitness\Meal\Models\Meal;
use Fitness\Meal\Tests\Concerns\CreatesMeals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * 照片分析：分析頁、上傳分析、確認頁帶入、存成 AI 餐點、信心度、失敗與上限、權限。
 */
class MealAnalysisTest extends TestCase
{
    use CreatesMeals, FakesFoodAnalyzer, RefreshDatabase;

    protected FakeFoodAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-21 12:40:00', 'Asia/Taipei'));
        $this->analyzer = $this->fakeFoodAnalyzer();
    }

    /**
     * 一張壓縮後大小的 JPEG 照片。
     */
    protected function photo(): UploadedFile
    {
        return UploadedFile::fake()->image('meal.jpg', 1600, 1200);
    }

    /**
     * 從分析頁送出照片分析。
     *
     * @param  array<string, mixed>  $data
     */
    protected function upload(array $data = []): TestResponse
    {
        return $this->from(route('fitness.meal.analyze', ['mode' => 'upload']))
            ->post(route('fitness.meal.analyze.store'), ['photo' => $this->photo(), ...$data]);
    }

    /**
     * 直接以分析服務建立一筆成功的分析（假 driver 的便當結果）。
     */
    protected function succeededAnalysis(User $user, ?string $hint = null): FoodAnalysis
    {
        return app(FoodAnalysisService::class)->analyze($user, $this->foodImage(), $hint);
    }

    /**
     * 確認頁送出的表單：分析結果的項目（含信心度），可以覆寫部分欄位。
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function aiPayload(FoodAnalysis $analysis, array $overrides = []): array
    {
        return [
            'date' => '2026-11-21',
            'time' => '12:31',
            'meal_type' => 'lunch',
            'note' => null,
            'food_analysis_id' => $analysis->id,
            'items' => array_map(fn ($item) => [...$item->toArray(), 'saved_food_id' => null], $analysis->items()),
            ...$overrides,
        ];
    }

    public function test_analyze_page(): void
    {
        $this->get(route('fitness.meal.analyze'))->assertRedirect(route('login'));

        $user = User::factory()->create();
        $this->succeededAnalysis($user);

        $this->actingAs($user)->get(route('fitness.meal.analyze', ['mode' => 'camera']))->assertInertia(fn (Assert $page) => $page
            ->component('meal/Analyze')
            ->where('mode', 'camera')
            ->where('today', '2026-11-21')
            ->where('quota', ['remaining' => 19, 'limit' => 20])
            ->where('hint_max_length', 200)
        );

        $this->get(route('fitness.meal.analyze', ['mode' => 'video']))->assertInertia(fn (Assert $page) => $page->where('mode', 'upload'));
        $this->get(route('fitness.meal.analyze'))->assertInertia(fn (Assert $page) => $page->where('mode', 'upload'));
    }

    public function test_upload_success_redirects_to_confirm_without_storing_photo(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->upload(['hint' => '飯只吃一半', 'taken_at' => '2026-11-21 08:15']);

        $analysis = FoodAnalysis::sole();
        $response->assertRedirect(route('fitness.meal.create', ['analysis' => $analysis->id, 'taken_at' => '2026-11-21 08:15']));
        $this->assertSame(FoodAnalysis::STATUS_SUCCEEDED, $analysis->status);
        $this->assertSame('飯只吃一半', $analysis->hint);

        $this->assertCount(1, $this->analyzer->calls);
        $this->assertSame('飯只吃一半', $this->analyzer->calls[0]['hint']);
        $this->assertSame('image/jpeg', $this->analyzer->calls[0]['image']->mimeType);
        $this->assertNotSame('', $this->analyzer->calls[0]['image']->contents);

        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles());

        // 沒有拍攝時間時不帶 taken_at
        $this->upload()->assertRedirect(route('fitness.meal.create', ['analysis' => FoodAnalysis::latest('id')->first()->id]));
    }

    public function test_confirm_uses_taken_at(): void
    {
        $user = User::factory()->create();
        $analysis = $this->succeededAnalysis($user);
        $this->actingAs($user);

        $confirm = fn (?string $takenAt) => $this->get(route('fitness.meal.create', array_filter(['analysis' => $analysis->id, 'taken_at' => $takenAt])));

        $confirm('2026-11-21 08:15')->assertInertia(fn (Assert $page) => $page
            ->where('defaults.date', '2026-11-21')
            ->where('defaults.time', '08:15')
            ->where('defaults.meal_type', 'breakfast')
            ->where('defaults.type_chosen', false)
            ->where('analysis.time_source', 'photo')
        );

        $confirm('2026-11-20 19:05')->assertInertia(fn (Assert $page) => $page
            ->where('defaults.date', '2026-11-20')
            ->where('defaults.time', '19:05')
            ->where('defaults.meal_type', 'dinner')
            ->where('analysis.time_source', 'photo')
        );

        foreach (['2026-11-21 13:00', '2026-11-21 8:15', '2026-02-31 08:00', 'yesterday', null] as $takenAt) {
            $confirm($takenAt)->assertInertia(fn (Assert $page) => $page
                ->where('defaults.date', '2026-11-21')
                ->where('defaults.time', '12:40')
                ->where('defaults.meal_type', 'lunch')
                ->where('analysis.time_source', 'now')
            );
        }
    }

    public function test_confirm_page_props(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->upload(['hint' => '滷蛋是半顆']);
        $analysis = FoodAnalysis::sole();

        $this->get(route('fitness.meal.create', ['analysis' => $analysis->id]))->assertInertia(fn (Assert $page) => $page
            ->component('meal/Form')
            ->where('meal', null)
            ->where('defaults.food_analysis_id', $analysis->id)
            ->where('defaults.copied_from', null)
            ->where('defaults.note', '')
            ->has('defaults.items', 4)
            ->where('defaults.items.0.name', '滷雞腿')
            ->where('defaults.items.0.kcal', 330)
            ->where('defaults.items.0.confidence', 0.86)
            ->where('defaults.items.3.confidence', 0.42)
            ->where('analysis.id', $analysis->id)
            ->where('analysis.item_count', 4)
            ->where('analysis.hint', '滷蛋是半顆')
            ->where('analysis.quota', ['remaining' => 19, 'limit' => 20])
        );
    }

    public function test_store_ai_meal(): void
    {
        $user = User::factory()->create();
        $analysis = $this->succeededAnalysis($user);
        $payload = $this->aiPayload($analysis);
        $payload['items'][1]['kcal'] = 140;
        $payload['items'][] = [...$this->food('無糖綠茶', 0), 'confidence' => null, 'saved_food_id' => null];

        $this->actingAs($user)
            ->post(route('fitness.meal.store'), $payload)
            ->assertRedirect(route('fitness.meal.index', ['date' => '2026-11-21']))
            ->assertSessionHas('success', '已儲存午餐');

        $meal = Meal::with('items')->sole();
        $this->assertSame(Meal::SOURCE_AI, $meal->source);
        $this->assertSame($analysis->id, $meal->food_analysis_id);
        $this->assertSame([0.86, 0.9, 0.68, 0.42, null], $meal->items->pluck('confidence')->all());
        $this->assertEquals(140, $meal->items[1]->kcal);
        $this->assertSame('無糖綠茶', $meal->items[4]->name);
    }

    public function test_analysis_can_only_create_one_meal(): void
    {
        $user = User::factory()->create();
        $analysis = $this->succeededAnalysis($user);
        $this->actingAs($user)->post(route('fitness.meal.store'), $this->aiPayload($analysis));
        $meal = Meal::sole();
        $error = ['title' => '這次的分析已經存成餐點了', 'message' => '如果要修改，請到飲食頁編輯那一餐。'];

        $this->post(route('fitness.meal.store'), $this->aiPayload($analysis))
            ->assertRedirect(route('fitness.meal.edit', $meal->id))
            ->assertSessionHas('error', $error);

        $this->get(route('fitness.meal.create', ['analysis' => $analysis->id]))
            ->assertRedirect(route('fitness.meal.edit', $meal->id))
            ->assertSessionHas('error', $error);

        $this->assertSame(1, Meal::count());
    }

    public function test_confidence_on_update_and_manual_meals(): void
    {
        $user = User::factory()->create();
        $analysis = $this->succeededAnalysis($user);
        $this->actingAs($user)->post(route('fitness.meal.store'), $this->aiPayload($analysis));
        $aiMeal = Meal::sole();

        $this->get(route('fitness.meal.edit', $aiMeal->id))->assertInertia(fn (Assert $page) => $page
            ->where('meal.source', 'ai')
            ->where('meal.items.3.confidence', 0.42)
        );

        $payload = $this->aiPayload($analysis, ['note' => '改過份量']);
        unset($payload['food_analysis_id']);
        $payload['items'][0]['portion'] = '半隻';
        $this->put(route('fitness.meal.update', $aiMeal->id), $payload)->assertSessionHasNoErrors();

        $aiMeal->refresh()->load('items');
        $this->assertSame('半隻', $aiMeal->items[0]->portion);
        $this->assertSame([0.86, 0.9, 0.68, 0.42], $aiMeal->items->pluck('confidence')->all());
        $this->assertSame($analysis->id, $aiMeal->food_analysis_id);

        $manual = [...$this->food('蛋餅', 380), 'confidence' => 0.5, 'saved_food_id' => null];
        $this->post(route('fitness.meal.store'), [
            'date' => '2026-11-21', 'time' => '07:50', 'meal_type' => 'breakfast', 'items' => [$manual],
        ])->assertSessionHasNoErrors();
        $manualMeal = Meal::where('source', Meal::SOURCE_MANUAL)->with('items')->sole();
        $this->assertNull($manualMeal->items[0]->confidence);
        $this->assertNull($manualMeal->food_analysis_id);

        $this->put(route('fitness.meal.update', $manualMeal->id), [
            'date' => '2026-11-21', 'time' => '07:50', 'meal_type' => 'breakfast', 'items' => [$manual],
        ])->assertSessionHasNoErrors();
        $this->assertNull($manualMeal->items()->sole()->confidence);
    }

    public function test_analysis_failure_returns_with_flash(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->analyzer->nextException = new NoFoodDetectedException;
        $this->upload()
            ->assertRedirect(route('fitness.meal.analyze', ['mode' => 'upload']))
            ->assertSessionHas('error', ['code' => 'no_food', 'title' => '照片裡看不出食物', 'message' => '換一張照片，或改手動輸入。', 'remaining' => 19]);

        $this->analyzer->nextException = new ProviderRateLimitedException('429');
        $this->upload()->assertSessionHas('error', [
            'code' => 'rate_limited', 'title' => 'Fake 暫時忙碌', 'message' => '免費額度暫時用完，稍等一下再試。', 'remaining' => 18,
        ]);

        $this->analyzer->nextException = new AnalysisTimeoutException('timeout');
        $this->upload()->assertSessionHas('error', fn (array $error) => $error['code'] === 'timeout' && $error['remaining'] === 17);

        $this->assertSame(3, FoodAnalysis::where('status', FoodAnalysis::STATUS_FAILED)->count());
        $this->assertSame(0, Meal::count());
    }

    public function test_analyze_validation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->upload(['photo' => UploadedFile::fake()->create('menu.pdf', 100, 'application/pdf')])
            ->assertSessionHasErrors(['photo' => '照片要是 JPEG、PNG 或 WebP。']);
        $this->upload(['photo' => UploadedFile::fake()->image('big.jpg')->size(5121)])
            ->assertSessionHasErrors(['photo' => '照片不能超過 5 MB。']);
        $this->upload(['photo' => null])->assertSessionHasErrors('photo');
        $this->upload(['hint' => str_repeat('湯', 201)])->assertSessionHasErrors('hint');
        $this->upload(['taken_at' => '2026/11/21 08:15'])->assertSessionHasErrors('taken_at');

        $this->upload(['photo' => UploadedFile::fake()->image('ok.png')])->assertSessionHasNoErrors();
        $this->assertCount(1, $this->analyzer->calls);
        $this->assertSame(1, FoodAnalysis::count());
    }

    public function test_other_users_or_failed_analysis(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $theirs = $this->succeededAnalysis($other);
        $this->analyzer->nextException = new NoFoodDetectedException;
        $this->actingAs($user)->upload();
        $failed = FoodAnalysis::where('status', FoodAnalysis::STATUS_FAILED)->sole();

        foreach ([$theirs, $failed] as $analysis) {
            $this->get(route('fitness.meal.create', ['analysis' => $analysis->id]))->assertNotFound();

            $this->post(route('fitness.meal.store'), $this->aiPayload($theirs, ['food_analysis_id' => $analysis->id]))
                ->assertSessionHasErrors(['food_analysis_id' => '找不到這次的照片分析，請重新拍照或上傳。']);
        }

        $this->get(route('fitness.meal.create', ['analysis' => 999999]))->assertNotFound();
        $this->assertSame(0, Meal::count());
    }

    public function test_daily_limit_on_upload(): void
    {
        config(['fitness.food_analyzer.daily_limit' => 1]);
        $user = User::factory()->create();
        $this->actingAs($user)->upload();

        $this->upload()->assertSessionHas('error', [
            'code' => 'daily_limit', 'title' => '今天的 1 次已用完', 'message' => '明天再試，或改手動輸入。', 'remaining' => 0,
        ]);
        $this->assertCount(1, $this->analyzer->calls);
        $this->assertSame(1, FoodAnalysis::count());

        $this->get(route('fitness.meal.analyze'))->assertInertia(fn (Assert $page) => $page->where('quota', ['remaining' => 0, 'limit' => 1]));
    }

    public function test_driver_unavailable_on_upload(): void
    {
        $user = User::factory()->create();
        $this->fakeFoodAnalyzer(configured: false);

        $this->actingAs($user)->upload()->assertSessionHas('error', fn (array $error) => $error['code'] === 'unavailable' && $error['remaining'] === 20);
        $this->assertSame(0, FoodAnalysis::count());
    }

    public function test_meal_source_in_lists(): void
    {
        $user = User::factory()->create();
        $this->meal($user, '2026-11-21', '07:50', 'breakfast', [$this->food('蛋餅', 380, 18, 42, 15)]);
        $this->actingAs($user)->post(route('fitness.meal.store'), $this->aiPayload($this->succeededAnalysis($user)));

        $this->get(route('fitness.meal.index'))->assertInertia(fn (Assert $page) => $page
            ->where('meals.0.source', 'manual')
            ->where('meals.1.source', 'ai')
        );

        $this->get(route('fitness.daily_summary.show'))->assertInertia(fn (Assert $page) => $page
            ->where('sections.meal.meals.0.source', 'manual')
            ->where('sections.meal.meals.1.source', 'ai')
        );
    }

    public function test_copy_ai_meal_drops_confidence(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('fitness.meal.store'), $this->aiPayload($this->succeededAnalysis($user)));
        $aiMeal = Meal::sole();

        $this->get(route('fitness.meal.create', ['copy_from' => $aiMeal->id]))->assertInertia(fn (Assert $page) => $page
            ->where('analysis', null)
            ->where('defaults.food_analysis_id', null)
            ->where('defaults.copied_from.id', $aiMeal->id)
            ->has('defaults.items', 4)
            ->missing('defaults.items.0.confidence')
            ->missing('defaults.items.3.confidence')
        );
    }
}
