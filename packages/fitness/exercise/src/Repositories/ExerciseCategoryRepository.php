<?php

namespace Fitness\Exercise\Repositories;

use Fitness\Exercise\Enums\RecordType;
use Fitness\Exercise\Models\ExerciseCategory;
use Fitness\Exercise\Repositories\Contracts\ExerciseCategoryRepository as ExerciseCategoryRepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 以 Eloquent 讀寫 exercise_categories。
 */
class ExerciseCategoryRepository implements ExerciseCategoryRepositoryContract
{
    /**
     * 使用者是否已有任何分類。
     */
    public function existsForUser(int $userId): bool
    {
        return ExerciseCategory::where('user_id', $userId)->exists();
    }

    /**
     * 依排序值，相同時依建立順序。
     */
    public function allForUser(int $userId): Collection
    {
        return $this->ordered($userId)->get()->toBase();
    }

    /**
     * 以 user_id 一起查詢，確保只能取得自己的分類。
     */
    public function findForUserOrFail(int $userId, int $id): ExerciseCategory
    {
        return ExerciseCategory::where('user_id', $userId)->findOrFail($id);
    }

    /**
     * 以 user_id 一起查詢，取不到時回傳 null。
     */
    public function findForUser(int $userId, int $id): ?ExerciseCategory
    {
        return ExerciseCategory::where('user_id', $userId)->find($id);
    }

    /**
     * 依排序取該記錄方式的第一個分類。
     */
    public function firstOfType(int $userId, RecordType $type): ?ExerciseCategory
    {
        return $this->ordered($userId)->where('record_type', $type->value)->first();
    }

    /**
     * 與唯一索引 (user_id, lower(name)) 使用相同的比對方式。
     */
    public function nameTaken(int $userId, string $name, ?int $exceptId = null): bool
    {
        return ExerciseCategory::where('user_id', $userId)
            ->whereRaw('lower(name) = lower(?)', [trim($name)])
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();
    }

    /**
     * 排序值取使用者目前最大值 + 1，新分類排在最後。
     */
    public function create(int $userId, array $attributes): ExerciseCategory
    {
        $position = (int) ExerciseCategory::where('user_id', $userId)->max('position') + 1;

        return ExerciseCategory::create([...$attributes, 'user_id' => $userId, 'position' => $position]);
    }

    /**
     * 更新分類。
     */
    public function update(ExerciseCategory $category, array $attributes): ExerciseCategory
    {
        $category->update($attributes);

        return $category;
    }

    /**
     * 先把使用者的分類依目前順序重新編號（排除重複或跳號），再與相鄰分類交換。
     */
    public function move(ExerciseCategory $category, int $step): bool
    {
        return DB::transaction(function () use ($category, $step) {
            $categories = $this->ordered($category->user_id)->lockForUpdate()->get()->values();
            $index = $categories->search(fn (ExerciseCategory $item) => $item->is($category));
            $target = $index === false ? null : $index + $step;

            if ($target === null || $target < 0 || $target >= $categories->count()) {
                return false;
            }

            [$categories[$index], $categories[$target]] = [$categories[$target], $categories[$index]];

            foreach ($categories as $position => $item) {
                if ($item->position !== $position + 1) {
                    $item->update(['position' => $position + 1]);
                }
            }

            return true;
        });
    }

    /**
     * 刪除分類。
     */
    public function delete(ExerciseCategory $category): void
    {
        $category->delete();
    }

    /**
     * 使用者分類的排序查詢。
     *
     * @return Builder<ExerciseCategory>
     */
    protected function ordered(int $userId): Builder
    {
        return ExerciseCategory::where('user_id', $userId)->orderBy('position')->orderBy('id');
    }
}
