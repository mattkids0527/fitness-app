<script setup lang="ts">
import { formatKcal } from '@/composables/useNutritionFormat';
import type { MealSummary } from '@/types/meal';
import { Link } from '@inertiajs/vue3';
import { Ellipsis } from 'lucide-vue-next';

defineProps<{
    meal: MealSummary;
}>();

const emit = defineEmits<{ menu: [] }>();
</script>

<template>
    <div class="flex items-center rounded-xl border bg-card">
        <Link :href="route('fitness.meal.edit', { meal: meal.id })" class="flex min-w-0 flex-1 items-center gap-2.5 py-3 pl-3.5">
            <div class="min-w-0 flex-1">
                <div class="truncate font-semibold">{{ meal.item_names.join('、') }}</div>
                <div class="mt-0.5 truncate text-xs text-muted-foreground">
                    {{ meal.time }} · {{ meal.item_names.length }} 個項目<template v-if="meal.note"> · {{ meal.note }}</template>
                </div>
            </div>
            <span class="whitespace-nowrap font-bold tabular-nums">
                {{ formatKcal(meal.kcal) }}<small class="ml-0.5 text-[11px] font-medium text-muted-foreground">kcal</small>
            </span>
        </Link>
        <button
            type="button"
            class="mx-1 grid size-9 shrink-0 place-items-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground"
            :aria-label="`${meal.time} 的餐點選項`"
            @click="emit('menu')"
        >
            <Ellipsis class="size-5" />
        </button>
    </div>
</template>
