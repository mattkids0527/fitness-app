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
