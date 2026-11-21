<?php

namespace Fitness\Exercise\Repositories;

use Fitness\Exercise\Models\Exercise;
use Fitness\Exercise\Repositories\Contracts\ExerciseRepository as ExerciseRepositoryContract;

/**
 * 以 Eloquent 讀寫 exercises。
 */
class ExerciseRepository implements ExerciseRepositoryContract
{
    /**
     * 以 user_id 一起查詢，確保只能取得自己的項目。
     */
    public function findForUserOrFail(int $userId, int $id): Exercise
    {
        return Exercise::where('user_id', $userId)->findOrFail($id);
    }

    /**
     * 新增項目。
     */
    public function create(int $userId, array $attributes): Exercise
    {
        return Exercise::create([...$attributes, 'user_id' => $userId]);
    }
}
