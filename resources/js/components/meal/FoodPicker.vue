<script setup lang="ts">
import SegmentedControl from '@/components/SegmentedControl.vue';
import { shortDate } from '@/composables/useDateFormat';
import { formatGrams, formatKcal } from '@/composables/useNutritionFormat';
import type { FoodValues, MealDetail, MealType, MealTypeOption, RecentFood, SavedFood } from '@/types/meal';
import { Copy, Plus } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    today: string;
    mealTypes: MealTypeOption[];
    savedFoods: SavedFood[];
    recentFoods: RecentFood[];
    copyableMeals: MealDetail[];
}>();

const emit = defineEmits<{
    pick: [food: FoodValues, savedFoodId: number | null];
    copy: [meal: MealDetail];
}>();

type Tab = 'saved' | 'recent' | 'copy';

const tab = ref<Tab | null>('saved');
const tabs: { value: Tab; label: string }[] = [
    { value: 'saved', label: '常用食物' },
    { value: 'recent', label: '最近吃過' },
    { value: 'copy', label: '複製整餐' },
];

/**
 * 份量、熱量與三大營養素的一行摘要。
 */
const foodMeta = (food: FoodValues) =>
    `${food.portion || '1 份'} · ${formatKcal(food.kcal)} kcal · 蛋白質 ${formatGrams(food.protein_g)} · 碳水 ${formatGrams(food.carbs_g)} · 脂肪 ${formatGrams(food.fat_g)}`;

/**
 * 餐別的中文名稱。
 */
const typeLabel = (value: MealType) => props.mealTypes.find((type) => type.value === value)?.label ?? '';
</script>

<template>
    <div>
        <SegmentedControl v-model="tab" :options="tabs" />

        <div class="mt-2.5 max-h-64 overflow-y-auto rounded-lg border md:max-h-96">
            <template v-if="tab === 'saved'">
                <button
                    v-for="food in savedFoods"
                    :key="food.id"
                    type="button"
                    class="flex w-full items-center gap-2.5 border-b px-3 py-2.5 text-left last:border-b-0 hover:bg-muted/60"
                    @click="emit('pick', food, food.id)"
                >
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-semibold">{{ food.name }}</div>
                        <div class="truncate text-xs text-muted-foreground">{{ foodMeta(food) }}</div>
                    </div>
                    <span class="grid size-7 shrink-0 place-items-center rounded-full bg-brand-soft text-primary"><Plus class="size-4" /></span>
                </button>
                <p v-if="!savedFoods.length" class="px-3 py-5 text-center text-sm text-muted-foreground">還沒有常用食物，在下方項目按 ☆ 就能加入。</p>
            </template>

            <template v-else-if="tab === 'recent'">
                <button
                    v-for="food in recentFoods"
                    :key="food.name"
                    type="button"
                    class="flex w-full items-center gap-2.5 border-b px-3 py-2.5 text-left last:border-b-0 hover:bg-muted/60"
                    @click="emit('pick', food, null)"
                >
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-semibold">{{ food.name }}</div>
                        <div class="truncate text-xs text-muted-foreground">
                            {{ food.portion || '1 份' }} · {{ formatKcal(food.kcal) }} kcal · {{ shortDate(food.last_eaten_on, today) }}
                        </div>
                    </div>
                    <span class="grid size-7 shrink-0 place-items-center rounded-full bg-brand-soft text-primary"><Plus class="size-4" /></span>
                </button>
                <p v-if="!recentFoods.length" class="px-3 py-5 text-center text-sm text-muted-foreground">近 30 天沒有紀錄。</p>
            </template>

            <template v-else>
                <button
                    v-for="meal in copyableMeals"
                    :key="meal.id"
                    type="button"
                    class="flex w-full items-center gap-2.5 border-b px-3 py-2.5 text-left last:border-b-0 hover:bg-muted/60"
                    @click="emit('copy', meal)"
                >
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-semibold">{{ meal.item_names.join('、') }}</div>
                        <div class="truncate text-xs text-muted-foreground">
                            {{ shortDate(meal.date, today) }} {{ typeLabel(meal.meal_type) }} · {{ meal.items.length }} 個項目 ·
                            {{ formatKcal(meal.kcal) }} kcal
                        </div>
                    </div>
                    <span class="grid size-7 shrink-0 place-items-center rounded-full bg-brand-soft text-primary"><Copy class="size-4" /></span>
                </button>
                <p v-if="!copyableMeals.length" class="px-3 py-5 text-center text-sm text-muted-foreground">近 14 天沒有餐點。</p>
            </template>
        </div>

        <p class="mt-1.5 text-xs text-muted-foreground">
            {{
                tab === 'saved'
                    ? '點選後選份數加入。'
                    : tab === 'recent'
                      ? '近 30 天吃過的項目，點選後選份數加入。'
                      : '近 14 天的餐點，點選後把整餐的項目加入。'
            }}
        </p>
    </div>
</template>
