<script setup lang="ts">
import WaterProgress from '@/components/water/WaterProgress.vue';
import WaterQuickButtons from '@/components/water/WaterQuickButtons.vue';
import { useQuickActions } from '@/composables/useQuickActions';
import type { WaterSummary } from '@/types/water';
import { ChevronRight, Droplet } from 'lucide-vue-next';

defineProps<{
    water: WaterSummary;
}>();

const { openWaterSheet } = useQuickActions();
</script>

<template>
    <section class="rounded-xl border bg-card p-4">
        <h2 class="mb-3 flex items-center gap-2 font-semibold">
            <Droplet class="size-4 text-sky-500" /> 喝水
            <button type="button" class="ml-auto flex items-center text-sm font-medium text-primary" @click="openWaterSheet">
                紀錄<ChevronRight class="size-4" />
            </button>
        </h2>
        <WaterProgress :water="water" />
        <WaterQuickButtons class="mt-3" :amounts="water.quick_amounts" />
    </section>
</template>
