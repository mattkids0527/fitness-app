<?php

namespace Fitness\Meal\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Fitness\FoodAnalyzer\Models\FoodAnalysis;
use Fitness\FoodAnalyzer\Services\FoodAnalysisService;
use Fitness\Meal\Enums\MealType;
use Fitness\Meal\Exceptions\FoodAnalysisAlreadyUsedException;
use Fitness\Meal\Models\Meal;
use Fitness\Meal\Models\MealItem;
use Fitness\Meal\Repositories\Contracts\MealRepository;
use Fitness\Meal\Repositories\Contracts\SavedFoodRepository;
use Fitness\Profile\Services\UserCalendar;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 餐點的查詢與存檔規則：以使用者時區切日、項目整批替換、帶入的常用食物更新最近使用時間；
 * 由照片分析建立的餐點標為 ai，一筆分析只能建立一餐。
 */
class MealService
{
    public function __construct(
        protected MealRepository $meals,
        protected SavedFoodRepository $savedFoods,
        protected FoodAnalysisService $analyzer,
    ) {}

    /**
     * 取得使用者自己的某一餐，不是自己的丟 ModelNotFoundException（回 404）。
     */
    public function find(User $user, int $mealId): Meal
    {
        return $this->meals->findForUserOrFail($user->id, $mealId);
    }

    /**
     * 取得使用者某一天（只看日期，以使用者時區的 00:00～24:00 切日）已確認的餐點，依時間由早到晚。
     *
     * @return Collection<int, Meal>
     */
    public function mealsOn(User $user, CarbonInterface $date): Collection
    {
        $start = CarbonImmutable::parse($date->toDateString(), UserCalendar::timezone($user))->startOfDay();

        return $this->meals->confirmedBetween($user->id, $start, $start->addDay());
    }

    /**
     * 「最近吃過」：近 N 天依名稱取最近一次。
     *
     * @return Collection<int, MealItem>
     */
    public function recentFoods(User $user): Collection
    {
        $since = UserCalendar::today($user)->subDays(config('fitness.meal.recent_foods_days') - 1);

        return $this->meals->recentItems($user->id, $since, config('fitness.meal.recent_foods_limit'));
    }

    /**
     * 「複製整餐」可選的餐點：近 N 天，排除正在編輯的那一餐。
     *
     * @return Collection<int, Meal>
     */
    public function copyableMeals(User $user, ?int $exceptMealId = null): Collection
    {
        $since = UserCalendar::today($user)->subDays(config('fitness.meal.copyable_meals_days') - 1);

        return $this->meals->confirmedSince($user->id, $since, config('fitness.meal.copyable_meals_limit'), $exceptMealId);
    }

    /**
     * 取得確認頁要帶入的照片分析：必須是自己成功的分析，否則丟 ModelNotFoundException（回 404）；
     * 已經建立過餐點時丟 FoodAnalysisAlreadyUsedException（導向那一餐）。
     *
     * @throws FoodAnalysisAlreadyUsedException
     */
    public function analysisForConfirm(User $user, int $foodAnalysisId): FoodAnalysis
    {
        $analysis = $this->analyzer->findSucceeded($user, $foodAnalysisId);
        abort_if($analysis === null, 404);

        $this->ensureAnalysisUnused($user, $analysis->id);

        return $analysis;
    }

    /**
     * 新增一餐（已確認）。帶 food_analysis_id 時為照片分析建立的餐點（source = ai），
     * 分析必須是自己成功、還沒建立過餐點的，否則分別丟 ValidationException、FoodAnalysisAlreadyUsedException。
     *
     * @param  array<string, mixed>  $data  已驗證的表單資料
     *
     * @throws ValidationException
     * @throws FoodAnalysisAlreadyUsedException
     */
    public function create(User $user, array $data): Meal
    {
        $analysisId = isset($data['food_analysis_id']) ? (int) $data['food_analysis_id'] : null;

        if ($analysisId !== null) {
            if ($this->analyzer->findSucceeded($user, $analysisId) === null) {
                throw ValidationException::withMessages(['food_analysis_id' => trans('FitnessMeal::errors.analysis_not_found')]);
            }

            $this->ensureAnalysisUnused($user, $analysisId);
        }

        try {
            return DB::transaction(function () use ($user, $data, $analysisId) {
                $meal = $this->meals->create($user->id, [
                    ...$this->mealAttributes($user, $data),
                    'source' => $analysisId === null ? Meal::SOURCE_MANUAL : Meal::SOURCE_AI,
                    'status' => Meal::STATUS_CONFIRMED,
                    'food_analysis_id' => $analysisId,
                ]);

                $this->saveItems($user, $meal, $data['items']);

                return $meal;
            });
        } catch (UniqueConstraintViolationException $e) {
            // 同一筆分析同時送出兩次：唯一索引擋下後一筆，導向先建立的那一餐
            $this->ensureAnalysisUnused($user, (int) $analysisId);

            throw $e;
        }
    }

    /**
     * 更新自己的一餐：時間、餐別、備註，項目整批替換。
     *
     * @param  array<string, mixed>  $data  已驗證的表單資料
     */
    public function update(User $user, int $mealId, array $data): Meal
    {
        $meal = $this->find($user, $mealId);

        return DB::transaction(function () use ($user, $meal, $data) {
            $this->meals->update($meal, $this->mealAttributes($user, $data));
            $this->saveItems($user, $meal, $data['items']);

            return $meal;
        });
    }

    /**
     * 刪除自己的一餐，回傳被刪除的餐點（用來決定導回哪一天）。
     */
    public function delete(User $user, int $mealId): Meal
    {
        $meal = $this->find($user, $mealId);
        $this->meals->delete($meal);

        return $meal;
    }

    /**
     * 日期與時間以使用者時區組成用餐時間。
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mealAttributes(User $user, array $data): array
    {
        return [
            'eaten_at' => CarbonImmutable::createFromFormat('Y-m-d H:i', $data['date'].' '.$data['time'], UserCalendar::timezone($user)),
            'meal_type' => MealType::from($data['meal_type']),
            'note' => $data['note'] ?? null,
        ];
    }

    /**
     * 這筆分析已經建立過餐點時丟 FoodAnalysisAlreadyUsedException。
     *
     * @throws FoodAnalysisAlreadyUsedException
     */
    protected function ensureAnalysisUnused(User $user, int $foodAnalysisId): void
    {
        $meal = $this->meals->findByFoodAnalysis($user->id, $foodAnalysisId);

        if ($meal !== null) {
            throw new FoodAnalysisAlreadyUsedException($meal);
        }
    }

    /**
     * 寫入項目，並把帶入的常用食物標記為剛使用過。信心度只保留在照片分析建立的餐點，手動餐點一律為 null。
     *
     * @param  list<array<string, mixed>>  $items
     */
    protected function saveItems(User $user, Meal $meal, array $items): void
    {
        $fields = ['name', 'portion', 'grams', 'kcal', 'protein_g', 'carbs_g', 'fat_g', 'fiber_g'];

        if ($meal->isFromPhoto()) {
            $fields[] = 'confidence';
        }

        $this->meals->replaceItems($meal, array_map(
            fn (array $item) => array_intersect_key($item, array_flip($fields)) + array_fill_keys($fields, null),
            $items,
        ));

        $savedFoodIds = collect($items)->pluck('saved_food_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        $this->savedFoods->touchUsed($user->id, $savedFoodIds, CarbonImmutable::now());
    }
}
