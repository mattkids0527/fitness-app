<?php

namespace Fitness\Exercise\Services;

use Carbon\CarbonInterface;
use Fitness\Profile\Models\WeightLog;
use Fitness\Profile\Repositories\Contracts\WeightLogRepository;

/**
 * 取得計算消耗熱量用的體重：紀錄日當天或之前最新一筆（透過 profile 的 Repository，不直接查體重表）。
 */
class BodyWeightResolver
{
    public function __construct(
        protected WeightLogRepository $weightLogs,
    ) {}

    /**
     * 取得某一天適用的體重紀錄；$date 為使用者時區的日期（Y-m-d 或日期時間）。沒有任何體重紀錄時為 null。
     */
    public function on(int $userId, CarbonInterface|string $date): ?WeightLog
    {
        $day = $date instanceof CarbonInterface ? $date->toDateString() : substr($date, 0, 10);

        return $this->weightLogs->effectiveOnDate($userId, $day);
    }
}
