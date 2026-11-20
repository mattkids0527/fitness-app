import type { FoodValues, MealFormItem, MealType, MealTypeOption } from '@/types/meal';

const NUMBER_FIELDS = ['grams', 'kcal', 'protein_g', 'carbs_g', 'fat_g', 'fiber_g'] as const;

let itemSeq = 0;

/**
 * 輸入框的值轉成數字；空值回傳 null（表示不知道）。
 */
export function toNumber(value: number | string | null | undefined): number | null {
    return value === '' || value === null || value === undefined || Number.isNaN(Number(value)) ? null : Number(value);
}

/**
 * 依份數換算重量與營養素（四捨五入到小數 1 位）；份數不是 1 時在份量描述加上倍數，與規格 §5 相同。
 */
export function scaleFood(food: FoodValues, servings: number): FoodValues {
    const scale = (value: number | null) => (value === null ? null : Math.round(value * servings * 10) / 10);

    return {
        name: food.name,
        portion: servings === 1 ? food.portion : `${food.portion || '1 份'} ×${servings}`,
        grams: scale(food.grams),
        kcal: scale(food.kcal) ?? 0,
        protein_g: scale(food.protein_g),
        carbs_g: scale(food.carbs_g),
        fat_g: scale(food.fat_g),
        fiber_g: scale(food.fiber_g),
    };
}

/**
 * 食物資料轉成表單項目（null 轉成空字串）。
 */
export function toFormItem(food: FoodValues | null = null, savedFoodId: number | null = null, open = false): MealFormItem {
    const item: MealFormItem = {
        key: ++itemSeq,
        name: food?.name ?? '',
        portion: food?.portion ?? '',
        grams: '',
        kcal: '',
        protein_g: '',
        carbs_g: '',
        fat_g: '',
        fiber_g: '',
        saved_food_id: savedFoodId,
        open,
    };

    NUMBER_FIELDS.forEach((field) => (item[field] = food?.[field] ?? ''));

    return item;
}

/**
 * 表單項目轉回送出的資料；熱量沒填時保留 null，讓後端回傳「熱量為必填」。
 */
export function fromFormItem(item: MealFormItem): Omit<FoodValues, 'kcal'> & { kcal: number | null; saved_food_id: number | null } {
    return {
        name: item.name.trim(),
        portion: item.portion.trim() || null,
        grams: toNumber(item.grams),
        kcal: toNumber(item.kcal),
        protein_g: toNumber(item.protein_g),
        carbs_g: toNumber(item.carbs_g),
        fat_g: toNumber(item.fat_g),
        fiber_g: toNumber(item.fiber_g),
        saved_food_id: item.saved_food_id,
    };
}

/**
 * 熱量沒填、但蛋白質／碳水／脂肪有填時，以 4／4／9 估算熱量；否則回傳 null。
 */
export function estimateKcal(item: MealFormItem): number | null {
    const [protein, carbs, fat] = [toNumber(item.protein_g), toNumber(item.carbs_g), toNumber(item.fat_g)];

    if (toNumber(item.kcal) !== null || (protein === null && carbs === null && fat === null)) {
        return null;
    }

    return Math.round((protein ?? 0) * 4 + (carbs ?? 0) * 4 + (fat ?? 0) * 9);
}

/**
 * 依時間（H:i）與後端給的時段推測餐別，都不符合時為點心；與後端 MealType::forTime() 相同。
 */
export function mealTypeForTime(time: string, types: MealTypeOption[]): MealType {
    const hour = Number(time.slice(0, 2));

    return types.find((type) => type.hours && hour >= type.hours[0] && hour < type.hours[1])?.value ?? 'snack';
}

/**
 * 裝置本地的現在時間（H:i）。
 */
export function currentTime(): string {
    const now = new Date();

    return `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
}
