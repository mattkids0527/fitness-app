<?php

namespace Fitness\Profile\Services;

use App\Models\User;
use Fitness\Profile\Exceptions\CannotDeleteLastWeightLogException;
use Fitness\Profile\Models\WeightLog;
use Fitness\Profile\Repositories\Contracts\WeightLogRepository;

/**
 * 體重紀錄的新增與刪除規則。
 */
class WeightLogService
{
    public function __construct(protected WeightLogRepository $weightLogs) {}

    /**
     * 新增一筆體重紀錄，未指定量測日時以使用者時區的今天為準。
     */
    public function record(User $user, float $weightKg, ?string $measuredOn = null): WeightLog
    {
        $measuredOn ??= UserCalendar::today($user)->toDateString();

        return $this->weightLogs->create($user->id, $weightKg, $measuredOn);
    }

    /**
     * 取得最新的 N 筆紀錄（新的在前），每筆附上與前一筆（較舊）的差值；多查一筆用來計算最後一筆的差值與判斷是否還有更多。
     *
     * @return array{items: list<array{id: int, weight_kg: float, measured_on: string, delta_kg: float|null}>, has_more: bool, total: int}
     */
    public function history(User $user, int $limit): array
    {
        $logs = $this->weightLogs->latestManyForUser($user->id, $limit + 1)->values();

        $items = $logs->take($limit)->map(function (WeightLog $log, int $index) use ($logs) {
            $previous = $logs->get($index + 1);

            return [
                'id' => $log->id,
                'weight_kg' => $log->weight_kg,
                'measured_on' => $log->measured_on->toDateString(),
                'delta_kg' => $previous ? round($log->weight_kg - $previous->weight_kg, 1) : null,
            ];
        })->all();

        return [
            'items' => $items,
            'has_more' => $logs->count() > $limit,
            'total' => $this->weightLogs->countForUser($user->id),
        ];
    }

    /**
     * 取得目前體重（最新一筆）與較前一筆的差值；沒有紀錄時回傳 null。
     *
     * @return array{id: int, weight_kg: float, measured_on: string, delta_kg: float|null}|null
     */
    public function current(User $user): ?array
    {
        return $this->history($user, 1)['items'][0] ?? null;
    }

    /**
     * 刪除使用者自己的紀錄；最後一筆不能刪，否則無法計算 BMR 與熱量目標。
     *
     * @throws CannotDeleteLastWeightLogException
     */
    public function delete(User $user, int $weightLogId): void
    {
        $weightLog = $this->weightLogs->findForUserOrFail($user->id, $weightLogId);

        if ($this->weightLogs->countForUser($user->id) <= 1) {
            throw new CannotDeleteLastWeightLogException;
        }

        $this->weightLogs->delete($weightLog);
    }
}
