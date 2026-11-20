<script setup lang="ts">
import { formatNumber } from '@/composables/useEnergyTargets';
import type { EnergyTargets } from '@/types/profile';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        targets: EnergyTargets;
        consumedKcal?: number;
        consumedProteinG?: number;
    }>(),
    { consumedKcal: 0, consumedProteinG: 0 },
);

const radius = 52;
const circumference = 2 * Math.PI * radius;
const remaining = computed(() => Math.round(props.targets.calorie_target - props.consumedKcal));
const over = computed(() => remaining.value < 0);
const progress = computed(() => Math.min(1, props.consumedKcal / props.targets.calorie_target));
const proteinLeft = computed(() => Math.max(0, Math.round(props.targets.protein_target_g - props.consumedProteinG)));
const proteinPercent = computed(() => Math.min(100, (props.consumedProteinG / props.targets.protein_target_g) * 100));
</script>

<template>
    <section class="rounded-xl border bg-card p-4">
        <div class="flex items-center gap-5">
            <div class="relative size-[120px] shrink-0">
                <svg viewBox="0 0 120 120" class="size-full -rotate-90" aria-hidden="true">
                    <circle cx="60" cy="60" :r="radius" fill="none" class="stroke-muted" stroke-width="12" />
                    <circle
                        cx="60"
                        cy="60"
                        :r="radius"
                        fill="none"
                        :class="over ? 'stroke-rose-600' : 'stroke-primary'"
                        stroke-width="12"
                        stroke-linecap="round"
                        :stroke-dasharray="circumference"
                        :stroke-dashoffset="circumference * (1 - progress)"
                    />
                </svg>
                <div class="absolute inset-0 grid place-content-center text-center">
                    <b :class="['text-2xl tabular-nums', over ? 'text-rose-600 dark:text-rose-500' : '']">{{ formatNumber(Math.abs(remaining)) }}</b>
                    <span class="text-[11px] text-muted-foreground">{{ over ? '超過 kcal' : '剩餘 kcal' }}</span>
                </div>
            </div>
            <div class="flex-1">
                <div class="text-sm text-muted-foreground">熱量目標</div>
                <div class="text-xl font-bold tabular-nums">{{ formatNumber(targets.calorie_target) }} kcal</div>
                <div class="mt-2 text-sm text-muted-foreground">已攝取</div>
                <div :class="['font-semibold tabular-nums', over ? 'text-rose-600 dark:text-rose-500' : '']">
                    {{ formatNumber(Math.round(consumedKcal)) }} kcal
                </div>
            </div>
        </div>
        <div class="mt-4">
            <div class="flex justify-between text-sm">
                <span>蛋白質</span>
                <span class="tabular-nums text-muted-foreground"
                    >{{ formatNumber(Math.round(consumedProteinG)) }} / {{ targets.protein_target_g }} g</span
                >
            </div>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-muted">
                <div class="h-full rounded-full bg-primary" :style="{ width: `${proteinPercent}%` }" />
            </div>
            <div class="mt-2 text-sm">
                <template v-if="proteinLeft > 0">
                    還差 <b>{{ proteinLeft }} g</b>
                </template>
                <b v-else>已達成蛋白質目標</b>
            </div>
        </div>
        <slot />
    </section>
</template>
