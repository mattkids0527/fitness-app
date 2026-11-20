<?php

namespace Fitness\Meal\Repositories;

use Carbon\CarbonInterface;
use Fitness\Meal\Models\SavedFood;
use Fitness\Meal\Repositories\Contracts\SavedFoodRepository as SavedFoodRepositoryContract;
use Illuminate\Support\Collection;

/**
 * 以 Eloquent 讀寫 saved_foods。
 */
class SavedFoodRepository implements SavedFoodRepositoryContract
{
    /**
     * 最近使用的在前，沒用過的（null）排最後，再依名稱排序。
     */
    public function allForUser(int $userId): Collection
    {
        return SavedFood::where('user_id', $userId)
            ->orderByRaw('last_used_at desc nulls last')
            ->orderByRaw('lower(name)')
            ->orderBy('id')
            ->get()
            ->toBase();
    }

    /**
     * 以 user_id 一起查詢，確保只能取得自己的常用食物。
     */
    public function findForUserOrFail(int $userId, int $id): SavedFood
    {
        return SavedFood::where('user_id', $userId)->findOrFail($id);
    }

    /**
     * 與唯一索引 (user_id, lower(name)) 使用相同的比對方式。
     */
    public function nameTaken(int $userId, string $name, ?int $exceptId = null): bool
    {
        return SavedFood::where('user_id', $userId)
            ->whereRaw('lower(name) = lower(?)', [trim($name)])
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();
    }

    /**
     * 新增常用食物。
     */
    public function create(int $userId, array $attributes): SavedFood
    {
        return SavedFood::create([...$attributes, 'user_id' => $userId]);
    }

    /**
     * 更新常用食物。
     */
    public function update(SavedFood $savedFood, array $attributes): SavedFood
    {
        $savedFood->update($attributes);

        return $savedFood;
    }

    /**
     * 刪除常用食物。
     */
    public function delete(SavedFood $savedFood): void
    {
        $savedFood->delete();
    }

    /**
     * 只更新屬於該使用者的 id，避免帶入別人的常用食物 id 時誤改。
     */
    public function touchUsed(int $userId, array $ids, CarbonInterface $at): void
    {
        if ($ids === []) {
            return;
        }

        SavedFood::where('user_id', $userId)
            ->whereIn('id', $ids)
            ->update(['last_used_at' => $at->format('Y-m-d H:i:sP')]);
    }
}
