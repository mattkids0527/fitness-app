<?php

namespace Fitness\Exercise\Repositories;

use Fitness\Exercise\Models\Exercise;
use Fitness\Exercise\Repositories\Contracts\ExerciseRepository as ExerciseRepositoryContract;
use Illuminate\Support\Collection;

/**
 * 以 Eloquent 讀寫 exercises。
 */
class ExerciseRepository implements ExerciseRepositoryContract
{
    /**
     * 依建立順序；分組與分類排序由呼叫端依分類處理。
     */
    public function activeForUser(int $userId): Collection
    {
        return Exercise::with('category')
            ->where('user_id', $userId)
            ->active()
            ->orderBy('id')
            ->get()
            ->toBase();
    }

    /**
     * 最近封存的在前，並帶出紀錄筆數。
     */
    public function archivedForUser(int $userId): Collection
    {
        return Exercise::with('category')
            ->withCount('logs')
            ->where('user_id', $userId)
            ->whereNotNull('archived_at')
            ->orderByDesc('archived_at')
            ->orderByDesc('id')
            ->get()
            ->toBase();
    }

    /**
     * 以 user_id 一起查詢，確保只能取得自己的項目。
     */
    public function findForUserOrFail(int $userId, int $id): Exercise
    {
        return Exercise::where('user_id', $userId)->findOrFail($id);
    }

    /**
     * 以 user_id 一起查詢，只取未封存的項目。
     */
    public function findActiveForUserOrFail(int $userId, int $id): Exercise
    {
        return Exercise::with('category')->withCount('logs')->where('user_id', $userId)->active()->findOrFail($id);
    }

    /**
     * 以 user_id 一起查詢，只取已封存的項目。
     */
    public function findArchivedForUserOrFail(int $userId, int $id): Exercise
    {
        return Exercise::where('user_id', $userId)->whereNotNull('archived_at')->findOrFail($id);
    }

    /**
     * 與部分唯一索引 (user_id, lower(name)) WHERE archived_at IS NULL 使用相同的比對方式。
     */
    public function nameTaken(int $userId, string $name, ?int $exceptId = null): bool
    {
        return Exercise::where('user_id', $userId)
            ->active()
            ->whereRaw('lower(name) = lower(?)', [trim($name)])
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();
    }

    /**
     * 分類底下未封存的項目數。
     */
    public function activeCountInCategory(int $categoryId): int
    {
        return Exercise::where('category_id', $categoryId)->active()->count();
    }

    /**
     * 項目是否已有健身紀錄。
     */
    public function hasLogs(Exercise $exercise): bool
    {
        return $exercise->logs()->exists();
    }

    /**
     * 新增項目。
     */
    public function create(int $userId, array $attributes): Exercise
    {
        return Exercise::create([...$attributes, 'user_id' => $userId]);
    }

    /**
     * 更新項目。
     */
    public function update(Exercise $exercise, array $attributes): Exercise
    {
        $exercise->update($attributes);

        return $exercise;
    }

    /**
     * 刪除項目。
     */
    public function delete(Exercise $exercise): void
    {
        $exercise->delete();
    }
}
