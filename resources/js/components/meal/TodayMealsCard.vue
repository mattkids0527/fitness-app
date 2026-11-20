<script setup lang="ts">
import MealTypeIcon from '@/components/meal/MealTypeIcon.vue';
import { Button } from '@/components/ui/button';
import { formatKcal } from '@/composables/useNutritionFormat';
import type { MealSummary, MealType, MealTypeOption } from '@/types/meal';
import { Link } from '@inertiajs/vue3';
import { ChevronRight, Plus, Utensils } from 'lucide-vue-next';

const props = defineProps<{
    meals: MealSummary[];
    mealTypes: MealTypeOption[];
}>();

/**
 * 餐別的中文名稱。
 */
const typeLabel = (value: MealType) => props.mealTypes.find((type) => type.value === value)?.label ?? '';
</script>

<template>
    <section class="rounded-xl border bg-card p-4">
        <h2 class="mb-1 flex items-center gap-2 font-semibold">
            <Utensils class="size-4" /> 今日餐點
            <Link :href="route('fitness.meal.index')" class="ml-auto flex items-center text-sm font-medium text-primary"
                >飲食頁<ChevronRight class="size-4"
            /></Link>
        </h2>
        <Link
            v-for="meal in meals"
            :key="meal.id"
            :href="route('fitness.meal.edit', { meal: meal.id })"
            class="flex items-center gap-2.5 border-b py-2.5 last:border-b-0"
        >
            <MealTypeIcon :type="meal.meal_type" />
            <div class="min-w-0 flex-1">
                <div class="text-xs text-muted-foreground">{{ typeLabel(meal.meal_type) }} · {{ meal.time }}</div>
                <div class="truncate text-sm font-semibold">{{ meal.item_names.join('、') }}</div>
            </div>
            <span class="whitespace-nowrap text-sm font-bold tabular-nums">
                {{ formatKcal(meal.kcal) }} <small class="font-medium text-muted-foreground">kcal</small>
            </span>
        </Link>
        <p v-if="!meals.length" class="mt-2 text-sm text-muted-foreground">今天還沒有記錄餐點。</p>
        <Button variant="outline" size="lg" class="mt-3 h-11 w-full" as-child>
            <Link :href="route('fitness.meal.create')"><Plus class="size-4" /> 記錄餐點</Link>
        </Button>
    </section>
</template>
