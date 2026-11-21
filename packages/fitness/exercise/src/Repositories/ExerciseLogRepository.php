<?php

namespace Fitness\Exercise\Repositories;

use Fitness\Exercise\Models\ExerciseLog;
use Fitness\Exercise\Repositories\Contracts\ExerciseLogRepository as ExerciseLogRepositoryContract;
use Illuminate\Support\Facades\DB;

/**
 * 以 Eloquent 讀寫 exercise_logs 與 exercise_sets。
 */
class ExerciseLogRepository implements ExerciseLogRepositoryContract
{
    /**
     * 以 user_id 一起查詢，確保只能取得自己的紀錄。
     */
    public function findForUserOrFail(int $userId, int $id): ExerciseLog
    {
        return ExerciseLog::with('sets')->where('user_id', $userId)->findOrFail($id);
    }

    /**
     * 紀錄與逐組在同一個交易內寫入，組別依陣列順序從 1 起編號。
     */
    public function create(int $userId, array $attributes, array $sets): ExerciseLog
    {
        return DB::transaction(function () use ($userId, $attributes, $sets) {
            $log = ExerciseLog::create([...$attributes, 'user_id' => $userId]);

            foreach (array_values($sets) as $index => $set) {
                $log->sets()->create([
                    'set_no' => $index + 1,
                    'reps' => $set['reps'],
                    'weight_kg' => $set['weight_kg'] ?? null,
                ]);
            }

            return $log->load('sets');
        });
    }
}
