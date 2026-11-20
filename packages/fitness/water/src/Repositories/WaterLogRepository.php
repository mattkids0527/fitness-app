<?php

namespace Fitness\Water\Repositories;

use Carbon\CarbonInterface;
use Fitness\Water\Models\WaterLog;
use Fitness\Water\Repositories\Contracts\WaterLogRepository as WaterLogRepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * 以 Eloquent 讀寫 water_logs。
 */
class WaterLogRepository implements WaterLogRepositoryContract
{
    /**
     * 取得某段時間的紀錄，新的在前。
     */
    public function between(int $userId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->queryBetween($userId, $from, $to)
            ->orderByDesc('drank_at')
            ->orderByDesc('id')
            ->get()
            ->toBase();
    }

    /**
     * 以 user_id 與時間範圍一起查詢，確保只能取得自己這段時間的紀錄。
     */
    public function findForUserBetweenOrFail(int $userId, int $id, CarbonInterface $from, CarbonInterface $to): WaterLog
    {
        return $this->queryBetween($userId, $from, $to)->findOrFail($id);
    }

    /**
     * 新增一筆紀錄。
     */
    public function create(int $userId, int $amountMl, CarbonInterface $drankAt): WaterLog
    {
        return WaterLog::create([
            'user_id' => $userId,
            'amount_ml' => $amountMl,
            'drank_at' => $drankAt,
        ]);
    }

    /**
     * 刪除一筆紀錄。
     */
    public function delete(WaterLog $waterLog): void
    {
        $waterLog->delete();
    }

    /**
     * 使用者在 [$from, $to) 之間的紀錄；時間以帶時區位移的字串綁定，避免以連線時區解讀。
     */
    protected function queryBetween(int $userId, CarbonInterface $from, CarbonInterface $to): Builder
    {
        return WaterLog::where('user_id', $userId)
            ->where('drank_at', '>=', $from->format('Y-m-d H:i:sP'))
            ->where('drank_at', '<', $to->format('Y-m-d H:i:sP'));
    }
}
