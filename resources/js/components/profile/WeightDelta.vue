<script setup lang="ts">
import { formatSigned } from '@/composables/useEnergyTargets';

defineProps<{
    delta: number | null;
}>();
</script>

<template>
    <span
        v-if="delta !== null"
        :class="[
            'text-xs font-semibold tabular-nums',
            delta < 0 ? 'text-emerald-600 dark:text-emerald-400' : delta > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-muted-foreground',
        ]"
    >
        {{ formatSigned(delta, 1) }}
    </span>
</template>
