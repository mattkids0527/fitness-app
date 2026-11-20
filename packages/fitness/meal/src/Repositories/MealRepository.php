<?php

namespace Fitness\Meal\Repositories;

use Carbon\CarbonInterface;
use Fitness\Meal\Models\Meal;
use Fitness\Meal\Models\MealItem;
use Fitness\Meal\Repositories\Contracts\MealRepository as MealRepositoryContract;
use Illuminate\Support\Collection;

/**
 * 以 Eloquent 讀寫 meals 與 meal_items。
 */
class MealRepository implements MealRepositoryContract
{
    /**
     * 以 user_id 一起查詢，確保只能取得自己的餐點。
     */
    public function findForUserOrFail(int $userId, int $id): Meal
    {
        return Meal::with('items')->where('user_id', $userId)->findOrFail($id);
    }

    /**
     * 以 user_id 與 food_analysis_id（唯一索引）查詢。
     */
    public function findByFoodAnalysis(int $userId, int $foodAnalysisId): ?Meal
    {
        return Meal::where('user_id', $userId)->where('food_analysis_id', $foodAnalysisId)->first();
    }

    /**
     * 取得某段時間內已確認的餐點，依用餐時間由早到晚。
     */
    public function confirmedBetween(int $userId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Meal::with('items')
            ->where('user_id', $userId)
            ->confirmed()
            ->where('eaten_at', '>=', $this->timestamp($from))
            ->where('eaten_at', '<', $this->timestamp($to))
            ->orderBy('eaten_at')
            ->orderBy('id')
            ->get()
            ->toBase();
    }

    /**
     * 取得某個時間之後已確認的餐點，新的在前。
     */
    public function confirmedSince(int $userId, CarbonInterface $since, int $limit, ?int $exceptMealId = null): Collection
    {
        return Meal::with('items')
            ->where('user_id', $userId)
            ->confirmed()
            ->where('eaten_at', '>=', $this->timestamp($since))
            ->when($exceptMealId, fn ($query) => $query->whereKeyNot($exceptMealId))
            ->orderByDesc('eaten_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->toBase();
    }

    /**
     * 以 PostgreSQL 的 DISTINCT ON 依名稱取最近一次，再依用餐時間新到舊取前 N 筆。
     */
    public function recentItems(int $userId, CarbonInterface $since, int $limit): Collection
    {
        $latestPerName = MealItem::query()
            ->join('meals', 'meals.id', '=', 'meal_items.meal_id')
            ->where('meals.user_id', $userId)
            ->where('meals.status', Meal::STATUS_CONFIRMED)
            ->where('meals.eaten_at', '>=', $this->timestamp($since))
            ->selectRaw('distinct on (lower(meal_items.name)) meal_items.*, meals.eaten_at as eaten_at')
            ->orderByRaw('lower(meal_items.name)')
            ->orderByDesc('meals.eaten_at')
            ->orderByDesc('meal_items.id');

        return MealItem::query()
            ->fromSub($latestPerName, 'meal_items')
            ->orderByDesc('eaten_at')
            ->limit($limit)
            ->get()
            ->toBase();
    }

    /**
     * 新增一餐。
     */
    public function create(int $userId, array $attributes): Meal
    {
        return Meal::create([...$attributes, 'user_id' => $userId]);
    }

    /**
     * 更新一餐的時間、餐別與備註。
     */
    public function update(Meal $meal, array $attributes): Meal
    {
        $meal->update($attributes);

        return $meal;
    }

    /**
     * 刪掉原本的項目後依順序重新建立，並重新載入關聯。
     */
    public function replaceItems(Meal $meal, array $items): void
    {
        $meal->items()->delete();

        foreach (array_values($items) as $position => $item) {
            $meal->items()->create([...$item, 'position' => $position]);
        }

        $meal->load('items');
    }

    /**
     * 刪除一餐。
     */
    public function delete(Meal $meal): void
    {
        $meal->delete();
    }

    /**
     * 時間條件以帶時區位移的字串綁定，避免查詢建構器以連線時區解讀。
     */
    protected function timestamp(CarbonInterface $time): string
    {
        return $time->format('Y-m-d H:i:sP');
    }
}
