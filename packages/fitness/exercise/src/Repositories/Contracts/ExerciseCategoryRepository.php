<?php

namespace Fitness\Exercise\Repositories\Contracts;

use Fitness\Exercise\Models\ExerciseCategory;

/**
 * 健身分類的存取介面；所有查詢都限定在指定使用者底下。
 */
interface ExerciseCategoryRepository
{
    /**
     * 使用者是否已有任何分類。
     */
    public function existsForUser(int $userId): bool;

    /**
     * 新增分類，排在使用者現有分類的最後。
     *
     * @param  array{name: string, record_type: string}  $attributes
     */
    public function create(int $userId, array $attributes): ExerciseCategory;
}
