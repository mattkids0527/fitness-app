<script setup lang="ts">
import { formatGrams } from '@/composables/useNutritionFormat';
import { computed } from 'vue';

const props = defineProps<{
    values: { protein_g: number | null; carbs_g: number | null; fat_g: number | null; fiber_g: number | null };
}>();

const nutrients = computed(() => [
    { label: '蛋白質', value: props.values.protein_g },
    { label: '碳水', value: props.values.carbs_g },
    { label: '脂肪', value: props.values.fat_g },
    { label: '纖維', value: props.values.fiber_g },
]);
</script>

<template>
    <div class="grid grid-cols-4 gap-1.5">
        <div v-for="nutrient in nutrients" :key="nutrient.label" class="rounded-lg bg-muted px-1.5 py-2 text-center">
            <div class="text-[11px] text-muted-foreground">{{ nutrient.label }}</div>
            <div class="mt-0.5 font-bold tabular-nums">
                {{ formatGrams(nutrient.value) }}<small class="text-[11px] font-medium text-muted-foreground">g</small>
            </div>
        </div>
    </div>
</template>
