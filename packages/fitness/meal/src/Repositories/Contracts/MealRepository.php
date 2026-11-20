<?php

namespace Fitness\Meal\Repositories\Contracts;

use Carbon\CarbonInterface;
use Fitness\Meal\Models\Meal;
use Fitness\Meal\Models\MealItem;
use Illuminate\Support\Collection;

/**
 * 餐點與食物項目的存取介面；所有查詢都限定在指定使用者底下，統計類查詢只取已確認的餐點。
 */
interface MealRepository
{
    /**
     * 取得使用者自己的某一餐（含項目），不屬於該使用者時丟 ModelNotFoundException。
     */
    public function findForUserOrFail(int $userId, int $id): Meal;

    /**
     * 取得使用者由某一筆照片分析建立的餐點；還沒建立時回傳 null。
     */
    public function findByFoodAnalysis(int $userId, int $foodAnalysisId): ?Meal;

    /**
     * 取得 [$from, $to) 之間已確認的餐點（含項目），依用餐時間由早到晚。
     *
     * @return Collection<int, Meal>
     */
    public function confirmedBetween(int $userId, CarbonInterface $from, CarbonInterface $to): Collection;

    /**
     * 取得 $since 之後已確認的餐點（含項目），新的在前，可排除指定的餐點。
     *
     * @return Collection<int, Meal>
     */
    public function confirmedSince(int $userId, CarbonInterface $since, int $limit, ?int $exceptMealId = null): Collection;

    /**
     * 取得 $since 之後吃過的食物項目，名稱相同（不分大小寫）只取最近一次，新的在前；每筆附 eaten_at。
     *
     * @return Collection<int, MealItem>
     */
    public function recentItems(int $userId, CarbonInterface $since, int $limit): Collection;

    /**
     * 新增一餐（不含項目）。
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(int $userId, array $attributes): Meal;

    /**
     * 更新一餐的時間、餐別與備註。
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Meal $meal, array $attributes): Meal;

    /**
     * 以新的項目整批取代這一餐原本的項目，順序依陣列順序。
     *
     * @param  list<array<string, mixed>>  $items
     */
    public function replaceItems(Meal $meal, array $items): void;

    /**
     * 刪除一餐（項目由外鍵 cascade 一併刪除）。
     */
    public function delete(Meal $meal): void;
}
