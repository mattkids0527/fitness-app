<?php

namespace Fitness\Water\Services;

use App\Models\User;
use Fitness\Profile\Repositories\Contracts\WeightLogRepository;

/**
 * 每日建議飲水量：目前體重 × 每公斤 ml，四捨五入到設定的單位；沒有體重紀錄時無法計算。
 */
class WaterTargetCalculator
{
    public function __construct(protected WeightLogRepository $weightLogs) {}

    /**
     * 以使用者目前（最新一筆）體重計算建議量；沒有體重紀錄時回傳 null。
     *
     * @return array{target_ml: int, weight_kg: float, ml_per_kg: int}|null
     */
    public function forUser(User $user): ?array
    {
        $weightKg = $this->weightLogs->latestForUser($user->id)?->weight_kg;

        if ($weightKg === null) {
            return null;
        }

        return [
            'target_ml' => $this->calculate($weightKg),
            'weight_kg' => $weightKg,
            'ml_per_kg' => (int) config('fitness.water.ml_per_kg'),
        ];
    }

    /**
     * 體重 × 每公斤 ml，四捨五入到 round_to_ml。
     */
    public function calculate(float $weightKg): int
    {
        $roundTo = (int) config('fitness.water.round_to_ml');

        return (int) (round($weightKg * (int) config('fitness.water.ml_per_kg') / $roundTo) * $roundTo);
    }
}
