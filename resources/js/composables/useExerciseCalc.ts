import type { ExerciseCalculation, RecordType } from '@/types/exercise';

/** 一組的次數與重量；空字串或 null 視為沒填。 */
export interface SetValues {
    reps: number | string | null;
    weight_kg?: number | string | null;
}

/**
 * 轉成數字；空字串、null、非數字為 0。
 */
function num(value: number | string | null | undefined): number {
    const n = Number(value);

    return value === '' || value === null || value === undefined || Number.isNaN(n) ? 0 : n;
}

/**
 * 四捨五入到小數 1 位（與後端 round(x, 1) 一致）。
 */
export function round1(value: number): number {
    return Math.round(value * 10) / 10;
}

/**
 * 消耗熱量（未四捨五入）：MET × 體重kg × 時數。
 */
export function kcalFor(met: number, weightKg: number, minutes: number): number {
    return (met * weightKg * minutes) / 60;
}

/**
 * 逐組估算秒數：Σ(次數 × 每下秒數) + (組數 − 1) × 組間休息；次數不是正數的組不計。
 */
export function setsSeconds(sets: SetValues[], secPerRep: number, restSec: number): number {
    const valid = sets.map((set) => Math.trunc(num(set.reps))).filter((reps) => reps > 0);

    if (!valid.length) {
        return 0;
    }

    return valid.reduce((sum, reps) => sum + reps, 0) * secPerRep + (valid.length - 1) * restSec;
}

/**
 * 訓練量 Σ(次數 × 重量)；沒填重量（徒手）的組不計。
 */
export function setsVolume(sets: SetValues[]): number {
    return sets.reduce((sum, set) => {
        const reps = Math.trunc(num(set.reps));
        const weight = num(set.weight_kg);

        return reps > 0 && weight > 0 ? sum + reps * weight : sum;
    }, 0);
}

/**
 * 建立／編輯項目時的「約消耗」估算：填時長以每 N 分鐘計，逐組以 N 組 × M 下計；kcal 四捨五入到小數 1 位。
 */
export function estimateKcal(
    type: RecordType,
    met: number,
    weightKg: number,
    calculation: ExerciseCalculation,
    secPerRep?: number | null,
    restSec?: number | null,
): number {
    const basis = calculation.estimate;

    if (type === 'sets') {
        const sets = Array.from({ length: basis.sets }, () => ({ reps: basis.reps }));
        const seconds = setsSeconds(sets, secPerRep ?? calculation.sets_defaults.sec_per_rep, restSec ?? calculation.sets_defaults.rest_sec);

        return round1(kcalFor(met, weightKg, seconds / 60));
    }

    return round1(kcalFor(met, weightKg, basis.duration_min));
}
