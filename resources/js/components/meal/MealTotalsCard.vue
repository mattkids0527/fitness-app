<script setup lang="ts">
import MissingNutrientsNotice from '@/components/meal/MissingNutrientsNotice.vue';
import NutrientGrid from '@/components/meal/NutrientGrid.vue';
import { shortDate } from '@/composables/useDateFormat';
import { formatKcal } from '@/composables/useNutritionFormat';
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
        <NutrientGrid class="mt-3" :values="totals" />
        <MissingNutrientsNotice v-if="hasMissingNutrients" />
    </section>
</template>
