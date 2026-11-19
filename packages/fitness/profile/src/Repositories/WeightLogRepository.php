<?php

namespace Fitness\Profile\Repositories;

use Fitness\Profile\Models\WeightLog;
use Fitness\Profile\Repositories\Contracts\WeightLogRepository as WeightLogRepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * 以 Eloquent 讀寫 weight_logs。
 */
class WeightLogRepository implements WeightLogRepositoryContract
{
    /**
     * 新增一筆體重紀錄。
     */
    public function create(int $userId, float $weightKg, string $measuredOn): WeightLog
    {
        return WeightLog::create([
            'user_id' => $userId,
            'weight_kg' => $weightKg,
            'measured_on' => $measuredOn,
        ]);
    }

    /**
     * 取得使用者最新一筆紀錄；同一天多筆時取最後建立的。
     */
    public function latestForUser(int $userId): ?WeightLog
    {
        return $this->newestFirst($userId)->first();
    }

    /**
     * 取得使用者最新的 N 筆紀錄，新的在前。
     */
    public function latestManyForUser(int $userId, int $limit): Collection
    {
        return $this->newestFirst($userId)->limit($limit)->get()->toBase();
    }

    /**
     * 以 user_id 一起查詢，確保只能取得自己的紀錄。
     */
    public function findForUserOrFail(int $userId, int $id): WeightLog
    {
        return WeightLog::where('user_id', $userId)->findOrFail($id);
    }

    /**
     * 計算使用者的紀錄筆數。
     */
    public function countForUser(int $userId): int
    {
        return WeightLog::where('user_id', $userId)->count();
    }

    /**
     * 刪除一筆紀錄。
     */
    public function delete(WeightLog $weightLog): void
    {
        $weightLog->delete();
    }

    /**
     * 使用者紀錄的共用排序：量測日新到舊，同日再依 id 新到舊。
     */
    protected function newestFirst(int $userId): Builder
    {
        return WeightLog::where('user_id', $userId)
            ->orderByDesc('measured_on')
            ->orderByDesc('id');
    }
}
