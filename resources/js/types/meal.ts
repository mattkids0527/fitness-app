export type MealType = 'breakfast' | 'lunch' | 'dinner' | 'snack';

/** 餐別選項；hours 為依時間自動選擇的時段 [起, 迄)，點心為 null。 */
export interface MealTypeOption {
    value: MealType;
    label: string;
    default_time: string;
    hours: [number, number] | null;
}

/** 食物的名稱、份量與營養素；營養素為 null 表示不知道。 */
export interface FoodValues {
    name: string;
    portion: string | null;
    grams: number | null;
    kcal: number;
    protein_g: number | null;
    carbs_g: number | null;
    fat_g: number | null;
    fiber_g: number | null;
}

/** 熱量與營養素加總（null 已當 0 計算）。 */
export interface NutritionTotals {
    kcal: number;
    protein_g: number;
    carbs_g: number;
    fat_g: number;
    fiber_g: number;
}

/** 列表用的餐點摘要；date、time 為使用者時區。 */
export interface MealSummary {
    id: number;
    date: string;
    time: string;
    meal_type: MealType;
    note: string | null;
    kcal: number;
    item_names: string[];
}
