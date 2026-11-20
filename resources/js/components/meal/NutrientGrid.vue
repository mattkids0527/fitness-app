<script setup lang="ts">
import { NUTRIENTS } from '@/composables/useNutrients';
import { formatGrams } from '@/composables/useNutritionFormat';

defineProps<{
    values: { protein_g: number | null; carbs_g: number | null; fat_g: number | null; fiber_g: number | null };
}>();
</script>

<template>
    <div class="grid grid-cols-4 gap-1.5">
        <div v-for="nutrient in NUTRIENTS" :key="nutrient.field" class="grid justify-items-center gap-0.5 rounded-lg bg-muted px-1 pb-1.5 pt-2">
            <component :is="nutrient.icon" :class="['size-4', nutrient.textClass]" aria-hidden="true" />
            <div class="font-bold tabular-nums">
                {{ formatGrams(values[nutrient.field]) }}<small class="text-[11px] font-medium text-muted-foreground">g</small>
            </div>
            <div class="text-[10px] leading-none text-muted-foreground">{{ nutrient.label }}</div>
        </div>
    </div>
</template>
