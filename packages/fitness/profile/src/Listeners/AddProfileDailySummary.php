<?php

namespace Fitness\Profile\Listeners;

use Fitness\DailySummary\Events\DailySummaryCollecting;
use Fitness\Profile\Services\EnergyTargetCalculator;
use Fitness\Profile\Services\WeightLogService;

/**
 * 把每日熱量／蛋白質目標與目前體重加入今日頁摘要。
 */
class AddProfileDailySummary
{
    public function __construct(
        protected EnergyTargetCalculator $calculator,
        protected WeightLogService $weightLogs,
    ) {}

    /**
     * 加入 profile 區塊：目標、目前體重，以及今天是否已量體重。
     */
    public function handle(DailySummaryCollecting $event): void
    {
        $currentWeight = $this->weightLogs->current($event->user);

        $event->addSection('profile', [
            'targets' => $this->calculator->forUser($event->user)?->toArray(),
            'current_weight' => $currentWeight,
            'weighed_today' => $currentWeight !== null && $currentWeight['measured_on'] === $event->date->toDateString(),
        ]);
    }
}
