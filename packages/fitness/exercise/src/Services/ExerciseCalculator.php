<?php

namespace Fitness\Exercise\Services;

use Fitness\Exercise\Enums\RecordType;

/**
 * 健身紀錄的熱量、時間與訓練量計算（純計算，不碰資料庫）：
 * kcal = MET × 體重kg × 時數；逐組的時間 = Σ(次數 × 每下秒數) + (組數 − 1) × 組間休息；訓練量 = Σ(次數 × 重量)。
 * 前端即時預覽的公式要與這裡一致。
 */
class ExerciseCalculator
{
    /**
     * 填時長紀錄的結果：時長即輸入值，沒有訓練量；數值四捨五入到小數 1 位。
     *
     * @return array{duration_min: float, kcal: float, volume_kg: null}
     */
    public function forDuration(float $met, float $weightKg, float $minutes): array
    {
        return [
            'duration_min' => round($minutes, 1),
            'kcal' => round($this->kcal($met, $weightKg, $minutes), 1),
            'volume_kg' => null,
        ];
    }

    /**
     * 逐組紀錄的結果：時長為估算時間；數值四捨五入到小數 1 位。
     *
     * @param  iterable<array{reps: int|float|string|null, weight_kg?: float|string|null}>  $sets
     * @return array{duration_min: float, kcal: float, volume_kg: float}
     */
    public function forSets(float $met, float $weightKg, iterable $sets, int $secPerRep, int $restSec): array
    {
        $minutes = $this->setsSeconds($sets, $secPerRep, $restSec) / 60;

        return [
            'duration_min' => round($minutes, 1),
            'kcal' => round($this->kcal($met, $weightKg, $minutes), 1),
            'volume_kg' => round($this->volume($sets), 1),
        ];
    }

    /**
     * 逐組估算秒數；次數不是正數的組不計（也不算進組間休息）。
     *
     * @param  iterable<array{reps: int|float|string|null}>  $sets
     */
    public function setsSeconds(iterable $sets, int $secPerRep, int $restSec): int
    {
        $reps = 0;
        $count = 0;

        foreach ($sets as $set) {
            $setReps = (int) ($set['reps'] ?? 0);

            if ($setReps > 0) {
                $reps += $setReps;
                $count++;
            }
        }

        return $count === 0 ? 0 : $reps * $secPerRep + ($count - 1) * $restSec;
    }

    /**
     * 訓練量 Σ(次數 × 重量)；沒填重量（徒手）的組不計。
     *
     * @param  iterable<array{reps: int|float|string|null, weight_kg?: float|string|null}>  $sets
     */
    public function volume(iterable $sets): float
    {
        $volume = 0.0;

        foreach ($sets as $set) {
            $reps = (int) ($set['reps'] ?? 0);
            $weight = (float) ($set['weight_kg'] ?? 0);

            if ($reps > 0 && $weight > 0) {
                $volume += $reps * $weight;
            }
        }

        return $volume;
    }

    /**
     * 消耗熱量（未四捨五入）：MET × 體重kg × 時數。
     */
    public function kcal(float $met, float $weightKg, float $minutes): float
    {
        return $met * $weightKg * $minutes / 60;
    }

    /**
     * 建立／編輯項目時的「約消耗」估算：填時長以每 N 分鐘計，逐組以 N 組 × M 下計（基準見設定 estimate）。
     *
     * @return array{duration_min: float, kcal: float, sets: int|null, reps: int|null}
     */
    public function estimate(RecordType $type, float $met, float $weightKg, ?int $secPerRep = null, ?int $restSec = null): array
    {
        $basis = config('fitness.exercise.estimate');

        if ($type->isSets()) {
            $sets = array_fill(0, (int) $basis['sets'], ['reps' => (int) $basis['reps']]);
            $result = $this->forSets(
                $met,
                $weightKg,
                $sets,
                $secPerRep ?? (int) config('fitness.exercise.sets_defaults.sec_per_rep'),
                $restSec ?? (int) config('fitness.exercise.sets_defaults.rest_sec'),
            );

            return ['duration_min' => $result['duration_min'], 'kcal' => $result['kcal'], 'sets' => (int) $basis['sets'], 'reps' => (int) $basis['reps']];
        }

        $result = $this->forDuration($met, $weightKg, (float) $basis['duration_min']);

        return ['duration_min' => $result['duration_min'], 'kcal' => $result['kcal'], 'sets' => null, 'reps' => null];
    }

    /**
     * 運動加到喝水建議量的 ml：運動時數 × 每小時 ml，四捨五入到設定的單位。
     */
    public function waterMl(float $minutes): int
    {
        $roundTo = (int) config('fitness.exercise.water_round_to_ml');
        $ml = $minutes / 60 * (int) config('fitness.exercise.water_ml_per_hour');

        return (int) (round($ml / $roundTo) * $roundTo);
    }
}
