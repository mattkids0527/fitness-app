<script setup lang="ts">
import { formatNumber } from '@/composables/useEnergyTargets';
import type { EnergyTargets } from '@/types/profile';
import { Info } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    targets: EnergyTargets;
}>();

// 飲食紀錄上線前攝取量為 0
const consumedKcal = 0;
const consumedProtein = 0;

const radius = 52;
const circumference = 2 * Math.PI * radius;
const remaining = computed(() => props.targets.calorie_target - consumedKcal);
const progress = computed(() => Math.min(1, consumedKcal / props.targets.calorie_target));
const proteinLeft = computed(() => Math.max(0, props.targets.protein_target_g - consumedProtein));
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
                        class="stroke-primary"
                        stroke-width="12"
                        stroke-linecap="round"
                        :stroke-dasharray="circumference"
                        :stroke-dashoffset="circumference * (1 - progress)"
                    />
                </svg>
                <div class="absolute inset-0 grid place-content-center text-center">
                    <b class="text-2xl tabular-nums">{{ formatNumber(remaining) }}</b>
                    <span class="text-[11px] text-muted-foreground">剩餘 kcal</span>
                </div>
            </div>
            <div class="flex-1">
                <div class="text-sm text-muted-foreground">熱量目標</div>
                <div class="text-xl font-bold tabular-nums">{{ formatNumber(targets.calorie_target) }} kcal</div>
                <div class="mt-2 text-sm text-muted-foreground">已攝取</div>
                <div class="font-semibold tabular-nums">{{ formatNumber(consumedKcal) }} kcal</div>
            </div>
        </div>
        <div class="mt-4">
            <div class="flex justify-between text-sm">
                <span>蛋白質</span>
                <span class="tabular-nums text-muted-foreground">{{ consumedProtein }} / {{ targets.protein_target_g }} g</span>
            </div>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-muted">
                <div class="h-full rounded-full bg-primary" :style="{ width: `${(consumedProtein / targets.protein_target_g) * 100}%` }" />
            </div>
            <div class="mt-2 text-sm">
                還差 <b>{{ proteinLeft }} g</b>
            </div>
        </div>
        <div class="mt-3 flex gap-2.5 rounded-lg bg-muted p-3 text-sm leading-relaxed text-muted-foreground">
            <Info class="mt-0.5 size-4 shrink-0" />
            <p>飲食紀錄上線後，這裡會即時顯示已攝取與剩餘熱量。</p>
        </div>
    </section>
</template>
