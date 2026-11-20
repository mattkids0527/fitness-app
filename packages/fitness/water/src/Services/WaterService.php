<?php

namespace Fitness\Water\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Fitness\Profile\Services\UserCalendar;
use Fitness\Water\Models\WaterLog;
use Fitness\Water\Repositories\Contracts\WaterLogRepository;

/**
 * 喝水紀錄的規則：只能記今天（記錄當下的時間）、只能刪自己今天的紀錄；以使用者時區切日。
 */
class WaterService
{
    public function __construct(
        protected WaterLogRepository $waterLogs,
        protected WaterTargetCalculator $targets,
    ) {}

    /**
     * 某一天（只看日期，以使用者時區切日）的喝水摘要：已喝總量、建議量、紀錄（新的在前），以及前端按鈕需要的設定。
     *
     * @return array{total_ml: int, target_ml: int|null, weight_kg: float|null, ml_per_kg: int, logs: list<array{id: int, amount_ml: int, time: string}>, quick_amounts: list<int>, min_ml: int, max_ml: int}
     */
    public function day(User $user, CarbonInterface $date): array
    {
        $timezone = UserCalendar::timezone($user);
        [$from, $to] = $this->dayRange($user, $date);
        $logs = $this->waterLogs->between($user->id, $from, $to);
        $target = $this->targets->forUser($user);

        return [
            'total_ml' => (int) $logs->sum('amount_ml'),
            'target_ml' => $target['target_ml'] ?? null,
            'weight_kg' => $target['weight_kg'] ?? null,
            'ml_per_kg' => (int) config('fitness.water.ml_per_kg'),
            'logs' => $logs->map(fn (WaterLog $log) => [
                'id' => $log->id,
                'amount_ml' => $log->amount_ml,
                'time' => $log->drank_at->setTimezone($timezone)->format('H:i'),
            ])->values()->all(),
            'quick_amounts' => array_map('intval', config('fitness.water.quick_amounts')),
            'min_ml' => (int) config('fitness.water.min_ml'),
            'max_ml' => (int) config('fitness.water.max_ml'),
        ];
    }

    /**
     * 今天（使用者時區）的喝水摘要。
     *
     * @return array<string, mixed>
     */
    public function today(User $user): array
    {
        return $this->day($user, UserCalendar::today($user));
    }

    /**
     * 記錄一筆喝水，時間為現在。
     */
    public function record(User $user, int $amountMl): WaterLog
    {
        return $this->waterLogs->create($user->id, $amountMl, CarbonImmutable::now());
    }

    /**
     * 刪除自己今天的一筆紀錄；別人的或不是今天的一律丟 ModelNotFoundException（回 404）。
     */
    public function deleteToday(User $user, int $waterLogId): void
    {
        [$from, $to] = $this->dayRange($user, UserCalendar::today($user));

        $this->waterLogs->delete($this->waterLogs->findForUserBetweenOrFail($user->id, $waterLogId, $from, $to));
    }

    /**
     * 使用者時區當天 00:00 到隔天 00:00。
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    protected function dayRange(User $user, CarbonInterface $date): array
    {
        $from = CarbonImmutable::parse($date->toDateString(), UserCalendar::timezone($user))->startOfDay();

        return [$from, $from->addDay()];
    }
}
