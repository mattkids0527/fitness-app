import { Drumstick, Leaf, Nut, Wheat } from 'lucide-vue-next';
import type { Component } from 'vue';

export type NutrientField = 'protein_g' | 'carbs_g' | 'fat_g' | 'fiber_g';

export interface NutrientMeta {
    field: NutrientField;
    label: string;
    icon: Component;
    // 圖示與文字色
    textClass: string;
    // 進度條等底色
    bgClass: string;
}

// 營養素的圖示與顏色只在這裡定義，各畫面共用，避免同一種營養素出現不同樣子
export const NUTRIENTS: readonly NutrientMeta[] = [
    { field: 'protein_g', label: '蛋白質', icon: Drumstick, textClass: 'text-rose-500 dark:text-rose-400', bgClass: 'bg-rose-500 dark:bg-rose-400' },
    { field: 'carbs_g', label: '碳水', icon: Wheat, textClass: 'text-amber-500 dark:text-amber-400', bgClass: 'bg-amber-500 dark:bg-amber-400' },
    { field: 'fat_g', label: '脂肪', icon: Nut, textClass: 'text-violet-500 dark:text-violet-400', bgClass: 'bg-violet-500 dark:bg-violet-400' },
    {
        field: 'fiber_g',
        label: '纖維',
        icon: Leaf,
        textClass: 'text-emerald-500 dark:text-emerald-400',
        bgClass: 'bg-emerald-500 dark:bg-emerald-400',
    },
];

// 三大營養素（列表摘要只顯示這三種）
export const MACROS = NUTRIENTS.filter((nutrient) => nutrient.field !== 'fiber_g');

/**
 * 取得某個營養素的圖示與顏色設定。
 */
export function nutrientMeta(field: NutrientField): NutrientMeta {
    return NUTRIENTS.find((nutrient) => nutrient.field === field)!;
}
