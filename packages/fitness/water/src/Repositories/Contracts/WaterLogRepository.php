<?php

namespace Fitness\Water\Repositories\Contracts;

use Carbon\CarbonInterface;
use Fitness\Water\Models\WaterLog;
use Illuminate\Support\Collection;

/**
 * 喝水紀錄的存取介面；所有查詢都限定在指定使用者底下。
 */
interface WaterLogRepository
{
    /**
     * 取得 [$from, $to) 之間的紀錄，新的在前。
     *
     * @return Collection<int, WaterLog>
     */
    public function between(int $userId, CarbonInterface $from, CarbonInterface $to): Collection;

    /**
     * 取得使用者自己、且喝水時間在 [$from, $to) 之間的某筆紀錄，找不到時丟 ModelNotFoundException。
     */
    public function findForUserBetweenOrFail(int $userId, int $id, CarbonInterface $from, CarbonInterface $to): WaterLog;

    /**
     * 新增一筆紀錄。
     */
    public function create(int $userId, int $amountMl, CarbonInterface $drankAt): WaterLog;

    /**
     * 刪除一筆紀錄。
     */
    public function delete(WaterLog $waterLog): void;
}
