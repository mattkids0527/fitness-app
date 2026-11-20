<script setup lang="ts">
import MissingNutrientsNotice from '@/components/meal/MissingNutrientsNotice.vue';
import { shortDate } from '@/composables/useDateFormat';
import { formatGrams, formatKcal } from '@/composables/useNutritionFormat';
import type { NutritionTotals } from '@/types/meal';
import { Utensils } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    date: string;
    today: string;
    totals: NutritionTotals;
    calorieTarget: number | null;
    hasMissingNutrients: boolean;
}>();

const nutrients = computed(() => [
    { label: '蛋白質', value: props.totals.protein_g },
    { label: '碳水', value: props.totals.carbs_g },
    { label: '脂肪', value: props.totals.fat_g },
    { label: '纖維', value: props.totals.fiber_g },
]);
const over = computed(() => props.calorieTarget !== null && props.totals.kcal > props.calorieTarget);
const progress = computed(() => (props.calorieTarget ? Math.min(100, (props.totals.kcal / props.calorieTarget) * 100) : 0));
</script>

<template>
    <section class="rounded-xl border bg-card p-4">
        <h2 class="mb-3 flex items-center gap-2 font-semibold"><Utensils class="size-4" /> {{ shortDate(date, today) }}合計</h2>
        <div class="text-4xl font-extrabold tabular-nums tracking-tight">
            {{ formatKcal(totals.kcal) }}
            <small class="ml-0.5 text-sm font-medium tracking-normal text-muted-foreground">
                {{ calorieTarget !== null ? `/ ${formatKcal(calorieTarget)} kcal` : 'kcal' }}
            </small>
        </div>
        <template v-if="calorieTarget !== null">
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-muted">
                <div :class="['h-full rounded-full', over ? 'bg-rose-600' : 'bg-primary']" :style="{ width: `${progress}%` }" />
            </div>
            <p :class="['mt-2 text-sm', over ? 'text-rose-600 dark:text-rose-500' : 'text-muted-foreground']">
                {{ over ? `超過 ${formatKcal(totals.kcal - calorieTarget)} kcal` : `還可以吃 ${formatKcal(calorieTarget - totals.kcal)} kcal` }}
            </p>
        </template>
        <div class="mt-3 grid grid-cols-4 gap-1.5">
            <div v-for="nutrient in nutrients" :key="nutrient.label" class="rounded-lg bg-muted px-1.5 py-2 text-center">
                <div class="text-[11px] text-muted-foreground">{{ nutrient.label }}</div>
                <div class="mt-0.5 font-bold tabular-nums">
                    {{ formatGrams(nutrient.value) }}<small class="text-[11px] font-medium text-muted-foreground">g</small>
                </div>
            </div>
        </div>
        <MissingNutrientsNotice v-if="hasMissingNutrients" />
    </section>
</template>
