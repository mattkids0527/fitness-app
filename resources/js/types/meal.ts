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

/** 編輯表單與複製整餐用的完整餐點（含項目）。 */
export interface MealDetail extends MealSummary {
    items: FoodValues[];
}

/** 常用食物（數值為一份）。 */
export interface SavedFood extends FoodValues {
    id: number;
    last_used_on: string | null;
}

/** 最近吃過的項目。 */
export interface RecentFood extends FoodValues {
    last_eaten_on: string;
}

/** 新增餐點頁的預設值；copied_from 為「再吃一次」的來源餐點。 */
export interface MealFormDefaults {
    date: string;
    time: string;
    meal_type: MealType;
    type_chosen: boolean;
    note: string;
    items: FoodValues[];
    copied_from: MealSummary | null;
}

export interface MealFormLimits {
    max_items: number;
    servings: number[];
}

/** 表單中的一個項目：數值欄位為輸入框的值（空字串表示沒填），key 與 open 只在前端使用。 */
export interface MealFormItem {
    key: number;
    name: string;
    portion: string;
    grams: number | string;
    kcal: number | string;
    protein_g: number | string;
    carbs_g: number | string;
    fat_g: number | string;
    fiber_g: number | string;
    saved_food_id: number | null;
    open: boolean;
}
