import type { Calculation, EnergyTargets, Gender, Goal, ProfileForm } from '@/types/profile';

/**
 * 依出生日期計算到指定日期（YYYY-MM-DD）的足歲。
 */
export function ageOn(birthDate: string, today: string): number | null {
    if (!birthDate || !today) {
        return null;
    }

    const [y, m, d] = birthDate.split('-').map(Number);
    const [ty, tm, td] = today.split('-').map(Number);

    return ty - y - (tm < m || (tm === m && td < d) ? 1 : 0);
}

/**
 * Mifflin-St Jeor 公式計算 BMR，與後端 BmrCalculator 相同。
 */
export function bmrOf(gender: Gender, weightKg: number, heightCm: number, age: number): number {
    return Math.round(10 * weightKg + 6.25 * heightCm - 5 * age + (gender === 'male' ? 5 : -161));
}

/**
 * 每週目標速度換算成每日熱量增減，與後端 EnergyTargetCalculator::dailyCalorieAdjustment() 相同。
 */
export function dailyAdjustment(goal: Goal | null, rate: number, kcalPerKg: number): number {
    if (!goal || goal === 'maintain' || !rate) {
        return 0;
    }

    const kcal = Math.round((rate * kcalPerKg) / 7);

    return goal === 'lose' ? -kcal : kcal;
}

/**
 * 以表單值與體重算出每日目標（格式同後端 EnergyTargets::toArray()）；資料不足時回傳 null。
 */
export function calculateTargets(form: ProfileForm, weightKg: number | string | null, today: string, calc: Calculation): EnergyTargets | null {
    const weight = Number(weightKg);
    const height = Number(form.height_cm);
    const age = ageOn(form.birth_date, today);

    if (!form.gender || !form.activity_level || !form.goal || !weight || !height || age === null || age < 0) {
        return null;
    }

    const bmrCalculated = bmrOf(form.gender, weight, height, age);
    const override = form.bmr_mode === 'measured' && Number(form.bmr_override) >= 500 ? Math.round(Number(form.bmr_override)) : null;
    const bmr = override ?? bmrCalculated;
    const tdee = Math.round(bmr * calc.activity_factors[form.activity_level]);
    const adjustment = dailyAdjustment(form.goal, Number(form.goal_rate_kg_per_week), calc.kcal_per_kg);
    const target = tdee + adjustment;
    const floor = calc.calorie_floor[form.gender];

    return {
        weight_kg: weight,
        age,
        bmr_calculated: bmrCalculated,
        bmr,
        bmr_overridden: override !== null,
        tdee,
        calorie_adjustment: adjustment,
        calorie_target: target,
        protein_target_g: Math.round(weight * calc.protein_g_per_kg[form.goal]),
        calorie_floor: floor,
        below_floor: target < floor,
    };
}

/**
 * 數字加千分位。
 */
export function formatNumber(value: number): string {
    return value.toLocaleString('en-US');
}

/**
 * 帶正負號的數字（用真正的減號），0 顯示 ±0。
 */
export function formatSigned(value: number, digits = 0): string {
    if (value === 0) {
        return '±0';
    }

    const abs = Math.abs(value).toLocaleString('en-US', { minimumFractionDigits: digits, maximumFractionDigits: digits });

    return `${value > 0 ? '+' : '−'}${abs}`;
}
