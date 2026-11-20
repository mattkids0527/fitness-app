<?php

namespace Fitness\Meal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Fitness\FoodAnalyzer\Drivers\FoodItem;
use Fitness\FoodAnalyzer\Models\FoodAnalysis;
use Fitness\FoodAnalyzer\Services\FoodAnalysisService;
use Fitness\Meal\Enums\MealType;
use Fitness\Meal\Http\Requests\MealRequest;
use Fitness\Meal\Models\Meal;
use Fitness\Meal\Services\MealPresenter;
use Fitness\Meal\Services\MealService;
use Fitness\Meal\Services\NutritionCalculator;
use Fitness\Meal\Services\SavedFoodService;
use Fitness\Profile\Services\EnergyTargetCalculator;
use Fitness\Profile\Services\UserCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 飲食頁與餐點的新增、修改、刪除，只能操作自己的餐點。
 */
class MealController extends Controller
{
    public function __construct(
        protected MealService $meals,
        protected MealPresenter $presenter,
    ) {}

    /**
     * 飲食頁：?date=Y-m-d（預設今天、不能是未來）當天的餐點與合計。
     */
    public function index(Request $request, NutritionCalculator $nutrition, EnergyTargetCalculator $targets, SavedFoodService $savedFoods): Response
    {
        $user = $request->user();
        $timezone = UserCalendar::timezone($user);
        $today = UserCalendar::today($user);
        $date = $this->resolveDate($request->query('date'), $today, $timezone);

        $meals = $this->meals->mealsOn($user, $date);
        $summary = $nutrition->summarize($meals->flatMap(fn (Meal $meal) => $meal->items));

        return Inertia::render('meal/Index', [
            'date' => $date->toDateString(),
            'today' => $today->toDateString(),
            'meals' => $meals->map(fn (Meal $meal) => $this->presenter->summary($meal, $timezone))->values(),
            'totals' => $summary['totals'],
            'has_missing_nutrients' => $summary['has_missing_nutrients'],
            'calorie_target' => $targets->forUser($user)?->calorieTarget,
            'meal_types' => MealType::options(),
            'saved_foods_count' => $savedFoods->all($user)->count(),
        ]);
    }

    /**
     * 新增餐點頁：?date=、?type= 預設日期與餐別；?copy_from={id} 以自己的某一餐預填（日期為今天、餐別沿用）；
     * ?analysis={id} 為照片分析的確認頁（見 confirmAnalysis()）。
     */
    public function create(Request $request, SavedFoodService $savedFoods, FoodAnalysisService $analyzer): Response
    {
        $user = $request->user();

        if ($request->filled('analysis')) {
            return $this->confirmAnalysis($request, $savedFoods, $analyzer);
        }

        $timezone = UserCalendar::timezone($user);
        $today = UserCalendar::today($user);
        $now = CarbonImmutable::now($timezone)->format('H:i');

        $copyFrom = $request->filled('copy_from') ? $this->meals->find($user, (int) $request->query('copy_from')) : null;
        $date = $copyFrom ? $today : $this->resolveDate($request->query('date'), $today, $timezone);
        $requestedType = MealType::tryFrom((string) $request->query('type'));
        $type = $copyFrom?->meal_type ?? $requestedType ?? MealType::forTime($now);

        return Inertia::render('meal/Form', [
            ...$this->formOptions($user, $savedFoods),
            'meal' => null,
            'defaults' => [
                'date' => $date->toDateString(),
                'time' => $date->equalTo($today) ? $now : $type->defaultTime(),
                'meal_type' => $type->value,
                'type_chosen' => $copyFrom !== null || $requestedType !== null,
                'note' => '',
                'items' => $copyFrom ? $copyFrom->items->map(fn ($item) => $this->presenter->food($item))->all() : [],
                'copied_from' => $copyFrom ? $this->presenter->summary($copyFrom, $timezone) : null,
                'food_analysis_id' => null,
            ],
            'analysis' => null,
        ]);
    }

    /**
     * 照片分析的確認頁：由伺服器讀回自己成功、還沒建立餐點的分析帶入項目（含信心度），重新整理也不會遺失。
     * ?taken_at=Y-m-d H:i（上傳照片的拍攝時間，使用者時區）有效且不是未來時用它當用餐時間，否則用現在；餐別依時間推測。
     */
    protected function confirmAnalysis(Request $request, SavedFoodService $savedFoods, FoodAnalysisService $analyzer): Response
    {
        $user = $request->user();
        $analysis = $this->meals->analysisForConfirm($user, (int) $request->query('analysis'));
        $takenAt = $this->resolveTakenAt($request->query('taken_at'), UserCalendar::timezone($user));
        $eatenAt = $takenAt ?? CarbonImmutable::now(UserCalendar::timezone($user));
        $time = $eatenAt->format('H:i');

        return Inertia::render('meal/Form', [
            ...$this->formOptions($user, $savedFoods),
            'meal' => null,
            'defaults' => [
                'date' => $eatenAt->toDateString(),
                'time' => $time,
                'meal_type' => MealType::forTime($time)->value,
                'type_chosen' => false,
                'note' => '',
                'items' => array_map(fn (FoodItem $item) => $item->toArray(), $analysis->items()),
                'copied_from' => null,
                'food_analysis_id' => $analysis->id,
            ],
            'analysis' => $this->analysisProps($analysis, $takenAt !== null, $analyzer, $user),
        ]);
    }

    /**
     * 確認頁的分析摘要：辨識出幾個項目、補充說明、用餐時間是否取自拍攝時間，以及今天剩餘次數。
     *
     * @return array<string, mixed>
     */
    protected function analysisProps(FoodAnalysis $analysis, bool $fromPhotoTime, FoodAnalysisService $analyzer, User $user): array
    {
        return [
            'id' => $analysis->id,
            'item_count' => count($analysis->result ?? []),
            'hint' => $analysis->hint,
            'time_source' => $fromPhotoTime ? 'photo' : 'now',
            'quota' => [
                'remaining' => $analyzer->remainingToday($user),
                'limit' => $analyzer->dailyLimit(),
            ],
        ];
    }

    /**
     * 解析拍攝時間 Y-m-d H:i（使用者時區）；格式不對或晚於現在時回傳 null。
     */
    protected function resolveTakenAt(mixed $value, string $timezone): ?CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
            return null;
        }

        $takenAt = CarbonImmutable::createFromFormat('!Y-m-d H:i', $value, $timezone);

        if (! $takenAt || $takenAt->format('Y-m-d H:i') !== $value || $takenAt->greaterThan(CarbonImmutable::now($timezone))) {
            return null;
        }

        return $takenAt;
    }

    /**
     * 新增一餐後回到飲食頁的那一天。
     */
    public function store(MealRequest $request): RedirectResponse
    {
        $meal = $this->meals->create($request->user(), $request->validated());

        return $this->backToDay($request->user(), $meal, 'meal_saved');
    }

    /**
     * 編輯餐點頁。
     */
    public function edit(Request $request, int $meal, SavedFoodService $savedFoods): Response
    {
        $user = $request->user();
        $model = $this->meals->find($user, $meal);

        return Inertia::render('meal/Form', [
            ...$this->formOptions($user, $savedFoods, $model->id),
            'meal' => $this->presenter->detail($model, UserCalendar::timezone($user)),
            'defaults' => null,
            'analysis' => null,
        ]);
    }

    /**
     * 修改自己的一餐後回到飲食頁的那一天。
     */
    public function update(MealRequest $request, int $meal): RedirectResponse
    {
        $model = $this->meals->update($request->user(), $meal, $request->validated());

        return $this->backToDay($request->user(), $model, 'meal_saved');
    }

    /**
     * 刪除自己的一餐後回到飲食頁的那一天。
     */
    public function destroy(Request $request, int $meal): RedirectResponse
    {
        $model = $this->meals->delete($request->user(), $meal);

        return $this->backToDay($request->user(), $model, 'meal_deleted');
    }

    /**
     * 新增／編輯頁共用的選項：餐別、快速帶入清單、上限。
     *
     * @return array<string, mixed>
     */
    protected function formOptions(User $user, SavedFoodService $savedFoods, ?int $editingMealId = null): array
    {
        $timezone = UserCalendar::timezone($user);

        return [
            'today' => UserCalendar::today($user)->toDateString(),
            'meal_types' => MealType::options(),
            'saved_foods' => $savedFoods->all($user)->map(fn ($food) => $this->presenter->savedFood($food, $timezone))->values(),
            'recent_foods' => $this->meals->recentFoods($user)->map(fn ($item) => $this->presenter->recentFood($item, $timezone))->values(),
            'copyable_meals' => $this->meals->copyableMeals($user, $editingMealId)->map(fn (Meal $meal) => $this->presenter->detail($meal, $timezone))->values(),
            'limits' => [
                'max_items' => (int) config('fitness.meal.max_items'),
                'servings' => config('fitness.meal.servings'),
            ],
        ];
    }

    /**
     * 導回飲食頁該餐的日期，並以 toast 顯示結果。
     */
    protected function backToDay(User $user, Meal $meal, string $messageKey): RedirectResponse
    {
        $date = $meal->eaten_at->setTimezone(UserCalendar::timezone($user))->toDateString();

        return redirect()
            ->route('fitness.meal.index', ['date' => $date])
            ->with('success', trans('FitnessMeal::messages.'.$messageKey, ['type' => $meal->meal_type->label()]));
    }

    /**
     * 解析 ?date=Y-m-d；格式不對或是未來日期時回傳今天。
     */
    protected function resolveDate(mixed $value, CarbonImmutable $today, string $timezone): CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $today;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, $timezone);

        if (! $date || $date->toDateString() !== $value || $date->greaterThan($today)) {
            return $today;
        }

        return $date;
    }
}
