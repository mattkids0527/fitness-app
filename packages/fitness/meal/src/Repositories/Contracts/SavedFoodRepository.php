<?php

namespace Fitness\Meal\Repositories\Contracts;

use Carbon\CarbonInterface;
use Fitness\Meal\Models\SavedFood;
use Illuminate\Support\Collection;

/**
 * 常用食物的存取介面；所有查詢都限定在指定使用者底下。
 */
interface SavedFoodRepository
{
    /**
     * 取得使用者所有常用食物，最近使用的在前，沒用過的依名稱排在最後。
     *
     * @return Collection<int, SavedFood>
     */
    public function allForUser(int $userId): Collection;

    /**
     * 取得使用者自己的某個常用食物，不屬於該使用者時丟 ModelNotFoundException。
     */
    public function findForUserOrFail(int $userId, int $id): SavedFood;

    /**
     * 名稱（不分大小寫、去頭尾空白）是否已被使用者的其他常用食物使用。
     */
    public function nameTaken(int $userId, string $name, ?int $exceptId = null): bool;

    /**
     * 新增常用食物。
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(int $userId, array $attributes): SavedFood;

    /**
     * 更新常用食物。
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(SavedFood $savedFood, array $attributes): SavedFood;

    /**
     * 刪除常用食物。
     */
    public function delete(SavedFood $savedFood): void;

    /**
     * 把使用者自己的這些常用食物標記為剛使用過；不屬於該使用者的 id 會被忽略。
     *
     * @param  list<int>  $ids
     */
    public function touchUsed(int $userId, array $ids, CarbonInterface $at): void;
}
