export type Gender = 'male' | 'female';
export type ActivityLevel = 'sedentary' | 'light' | 'moderate' | 'active';
export type Goal = 'lose' | 'maintain' | 'gain';
export type BmrMode = 'formula' | 'measured';

export interface Option<T extends string = string> {
    value: T;
    label: string;
    description?: string;
}

export interface RateRange {
    min: number;
    max: number;
}

/** 與後端設定一致的計算係數，前端即時預覽用。 */
export interface Calculation {
    activity_factors: Record<ActivityLevel, number>;
    kcal_per_kg: number;
    protein_g_per_kg: Record<Goal, number>;
    calorie_floor: Record<Gender, number>;
    min_age: number;
}

export interface ProfileOptions {
    genders: Option<Gender>[];
    activity_levels: Option<ActivityLevel>[];
    goals: Option<Goal>[];
    goal_rate: Record<'lose' | 'gain', RateRange>;
    calculation: Calculation;
}

/** users 表上的 profile 欄位。 */
export interface Profile {
    gender: Gender | null;
    birth_date: string | null;
    height_cm: number | null;
    activity_level: ActivityLevel | null;
    goal: Goal | null;
    goal_rate_kg_per_week: number | null;
    bmr_override: number | null;
    timezone: string | null;
}

/** 對應後端 EnergyTargets::toArray()。 */
export interface EnergyTargets {
    weight_kg: number;
    age: number;
    bmr_calculated: number;
    bmr: number;
    bmr_overridden: boolean;
    tdee: number;
    calorie_adjustment: number;
    calorie_target: number;
    protein_target_g: number;
    calorie_floor: number;
    below_floor: boolean;
}

export interface WeightLogItem {
    id: number;
    weight_kg: number;
    measured_on: string;
    delta_kg: number | null;
}

export interface WeightHistory {
    items: WeightLogItem[];
    has_more: boolean;
    total: number;
}

/** 首次設定與編輯頁共用的表單欄位。 */
export interface ProfileForm {
    gender: Gender | null;
    birth_date: string;
    height_cm: string | number;
    activity_level: ActivityLevel | null;
    goal: Goal | null;
    goal_rate_kg_per_week: string | number;
    bmr_mode: BmrMode;
    bmr_override: string | number;
}
