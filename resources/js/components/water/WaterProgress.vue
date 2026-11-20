<script setup lang="ts">
import type { WaterSummary } from '@/types/water';
import { computed } from 'vue';

const props = defineProps<{
    water: WaterSummary;
}>();

const done = computed(() => props.water.target_ml !== null && props.water.total_ml >= props.water.target_ml);
const percent = computed(() => (props.water.target_ml ? Math.min(100, (props.water.total_ml / props.water.target_ml) * 100) : 0));
</script>

<template>
    <div>
        <div class="flex items-baseline justify-between gap-2">
            <div class="text-3xl font-extrabold tabular-nums tracking-tight">
                {{ water.total_ml.toLocaleString('en-US') }}
                <small class="text-sm font-medium tracking-normal text-muted-foreground">
                    {{ water.target_ml !== null ? `/ ${water.target_ml.toLocaleString('en-US')} ml` : 'ml' }}
                </small>
            </div>
            <span
                v-if="water.target_ml !== null"
                :class="['text-sm', done ? 'font-semibold text-sky-600 dark:text-sky-400' : 'text-muted-foreground']"
            >
                {{ done ? '已達成' : `還差 ${(water.target_ml - water.total_ml).toLocaleString('en-US')} ml` }}
            </span>
        </div>
        <div v-if="water.target_ml !== null" class="mt-2 h-2 overflow-hidden rounded-full bg-muted">
            <div class="h-full rounded-full bg-sky-500" :style="{ width: `${percent}%` }" />
        </div>
        <p v-else class="mt-1 text-sm text-muted-foreground">記錄體重後會顯示每日建議量。</p>
    </div>
</template>
