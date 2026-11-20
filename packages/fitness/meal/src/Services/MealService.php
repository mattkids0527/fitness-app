<?php

namespace Fitness\Meal\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Fitness\Meal\Enums\MealType;
use Fitness\Meal\Models\Meal;
use Fitness\Meal\Models\MealItem;
use Fitness\Meal\Repositories\Contracts\MealRepository;
use Fitness\Meal\Repositories\Contracts\SavedFoodRepository;
use Fitness\Profile\Services\UserCalendar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 餐點的查詢與存檔規則：以使用者時區切日、項目整批替換、帶入的常用食物更新最近使用時間。
 */
class MealService
{
    public function __construct(
        protected MealRepository $meals,
        protected SavedFoodRepository $savedFoods,
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
     * 新增一餐（手動、已確認），在交易內建立餐點與項目。
     *
     * @param  array<string, mixed>  $data  已驗證的表單資料
     */
    public function create(User $user, array $data): Meal
    {
        return DB::transaction(function () use ($user, $data) {
            $meal = $this->meals->create($user->id, [
                ...$this->mealAttributes($user, $data),
                'source' => Meal::SOURCE_MANUAL,
                'status' => Meal::STATUS_CONFIRMED,
            ]);

            $this->saveItems($user, $meal, $data['items']);

            return $meal;
        });
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
     * 寫入項目，並把帶入的常用食物標記為剛使用過。
     *
     * @param  list<array<string, mixed>>  $items
     */
    protected function saveItems(User $user, Meal $meal, array $items): void
    {
        $fields = ['name', 'portion', 'grams', 'kcal', 'protein_g', 'carbs_g', 'fat_g', 'fiber_g'];

        $this->meals->replaceItems($meal, array_map(
            fn (array $item) => array_intersect_key($item, array_flip($fields)) + array_fill_keys($fields, null),
            $items,
        ));

        $savedFoodIds = collect($items)->pluck('saved_food_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        $this->savedFoods->touchUsed($user->id, $savedFoodIds, CarbonImmutable::now());
    }
}
