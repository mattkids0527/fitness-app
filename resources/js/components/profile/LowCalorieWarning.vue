<script setup lang="ts">
import { formatNumber } from '@/composables/useEnergyTargets';
import type { EnergyTargets, Gender } from '@/types/profile';
import { TriangleAlert } from 'lucide-vue-next';

defineProps<{
    targets: EnergyTargets | null;
    gender: Gender | null;
}>();
</script>

<template>
    <div
        v-if="targets?.below_floor"
        class="mt-3 flex gap-2.5 rounded-lg bg-amber-500/10 p-3 text-sm leading-relaxed text-amber-800 dark:text-amber-400"
        role="alert"
    >
        <TriangleAlert class="mt-0.5 size-4 shrink-0" />
        <p>
            每日熱量目標低於建議下限（{{ gender === 'female' ? '女性' : '男性' }}
            {{ formatNumber(targets.calorie_floor) }} kcal），長期可能流失肌肉、影響代謝，建議放慢每週速度。
        </p>
    </div>
</template>
