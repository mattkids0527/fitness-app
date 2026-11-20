<script setup lang="ts">
import { formatKcal } from '@/composables/useNutritionFormat';
import type { MealSummary } from '@/types/meal';
import { Link } from '@inertiajs/vue3';
import { Camera, Ellipsis } from 'lucide-vue-next';

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
                <div class="mt-0.5 flex min-w-0 items-center gap-1.5 text-xs text-muted-foreground">
                    <span class="shrink-0">{{ meal.time }}</span>
                    <span
                        v-if="meal.source === 'ai'"
                        class="inline-flex shrink-0 items-center gap-0.5 text-[11px] font-semibold text-primary"
                        title="由照片分析建立"
                    >
                        <Camera class="size-3" />照片
                    </span>
                    <span v-if="meal.note" class="truncate">· {{ meal.note }}</span>
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
