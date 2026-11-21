<?php

namespace Fitness\Exercise\Repositories\Contracts;

use Fitness\Exercise\Models\ExerciseLog;

/**
 * 健身紀錄的存取介面；所有查詢都限定在指定使用者底下。
 */
interface ExerciseLogRepository
{
    /**
     * 取得使用者自己的某筆紀錄（含逐組），不屬於該使用者時丟 ModelNotFoundException。
     */
    public function findForUserOrFail(int $userId, int $id): ExerciseLog;

    /**
     * 新增紀錄與逐組明細（填時長的紀錄 $sets 為空陣列）。
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<array{reps: int, weight_kg: float|null}>  $sets
     */
    public function create(int $userId, array $attributes, array $sets): ExerciseLog;
}
