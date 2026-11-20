<?php

namespace Fitness\Water\Listeners;

use Fitness\DailySummary\Events\DailySummaryCollecting;
use Fitness\Water\Services\WaterService;

/**
 * 把當日喝水量、建議量與紀錄加入今日頁摘要。
 */
class AddWaterDailySummary
{
    public function __construct(protected WaterService $water) {}

    /**
     * 加入 water 區塊（格式同共享資料 water）。
     */
    public function handle(DailySummaryCollecting $event): void
    {
        $event->addSection('water', $this->water->day($event->user, $event->date));
    }
}
