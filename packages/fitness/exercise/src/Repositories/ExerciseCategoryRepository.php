<?php

namespace Fitness\Exercise\Repositories;

use Fitness\Exercise\Models\ExerciseCategory;
use Fitness\Exercise\Repositories\Contracts\ExerciseCategoryRepository as ExerciseCategoryRepositoryContract;

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
     * 排序值取使用者目前最大值 + 1，新分類排在最後。
     */
    public function create(int $userId, array $attributes): ExerciseCategory
    {
        $position = (int) ExerciseCategory::where('user_id', $userId)->max('position') + 1;

        return ExerciseCategory::create([...$attributes, 'user_id' => $userId, 'position' => $position]);
    }
}
