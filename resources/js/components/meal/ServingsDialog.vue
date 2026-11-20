<script setup lang="ts">
import NutrientGrid from '@/components/meal/NutrientGrid.vue';
import ResponsiveDialog from '@/components/ResponsiveDialog.vue';
import { Button } from '@/components/ui/button';
import { scaleFood } from '@/composables/useMealFood';
import { formatGrams, formatKcal } from '@/composables/useNutritionFormat';
import type { FoodValues } from '@/types/meal';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    food: FoodValues | null;
    servings: number[];
}>();

const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ add: [food: FoodValues, servings: number] }>();

const selected = ref(1);
const scaled = computed(() => (props.food ? scaleFood(props.food, selected.value) : null));

// 每次選了新的食物都從 1 份開始
watch(
    () => props.food,
    () => (selected.value = 1),
);

/**
 * 以選定的份數加入。
 */
function add() {
    if (scaled.value) {
        emit('add', scaled.value, selected.value);
    }
}
</script>

<template>
    <ResponsiveDialog
        v-model:open="open"
        :title="food?.name ?? ''"
        :description="food ? `一份：${food.portion || '1 份'} · ${formatKcal(food.kcal)} kcal` : undefined"
    >
        <template v-if="scaled">
            <span class="mb-2 block text-sm font-semibold">份數</span>
            <div class="grid grid-cols-4 gap-1.5">
                <button
                    v-for="option in servings"
                    :key="option"
                    type="button"
                    :aria-pressed="selected === option"
                    :class="[
                        'h-11 rounded-lg border-[1.5px] font-semibold tabular-nums',
                        selected === option ? 'border-primary bg-brand-soft text-primary' : 'border-input bg-card',
                    ]"
                    @click="selected = option"
                >
                    ×{{ option }}
                </button>
            </div>
            <div class="mt-3 rounded-xl border bg-card p-4">
                <div class="text-sm text-muted-foreground">
                    {{ scaled.portion || '1 份' }}<template v-if="scaled.grams !== null"> · {{ formatGrams(scaled.grams) }} g</template>
                </div>
                <div class="text-3xl font-extrabold tabular-nums tracking-tight">
                    {{ formatKcal(scaled.kcal) }}<small class="ml-1 text-sm font-medium tracking-normal text-muted-foreground">kcal</small>
                </div>
                <NutrientGrid class="mt-3" :values="scaled" />
            </div>
            <div class="mt-5 flex gap-2">
                <Button variant="outline" size="lg" class="h-11 flex-1" @click="open = false">取消</Button>
                <Button size="lg" class="h-11 flex-1" @click="add">加入</Button>
            </div>
        </template>
    </ResponsiveDialog>
</template>
