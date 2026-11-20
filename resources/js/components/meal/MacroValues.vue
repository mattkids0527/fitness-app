<script setup lang="ts">
import { MACROS } from '@/composables/useNutrients';
import { formatGrams } from '@/composables/useNutritionFormat';

defineProps<{
    values: { protein_g: number | null; carbs_g: number | null; fat_g: number | null };
}>();
</script>

<template>
    <!-- 列表用的三大營養素：彩色圖示＋克數，名稱只給螢幕閱讀器 -->
    <span class="inline-flex items-center gap-2 tabular-nums">
        <span v-for="nutrient in MACROS" :key="nutrient.field" class="inline-flex items-center gap-0.5">
            <component :is="nutrient.icon" :class="['size-3 shrink-0', nutrient.textClass]" aria-hidden="true" />
            <span class="sr-only">{{ nutrient.label }}</span>
            {{ formatGrams(values[nutrient.field as 'protein_g' | 'carbs_g' | 'fat_g']) }}
        </span>
    </span>
</template>
