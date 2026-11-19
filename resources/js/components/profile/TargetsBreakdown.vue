<script setup lang="ts">
import { formatNumber, formatSigned } from '@/composables/useEnergyTargets';
import type { EnergyTargets, Goal } from '@/types/profile';
import { computed } from 'vue';

const props = defineProps<{
    targets: EnergyTargets;
    activityLabel: string;
    activityFactor: number;
    goal: Goal;
    rate: number | null;
}>();

const rateText = computed(() =>
    props.goal === 'maintain' || !props.rate ? '維持' : `每週 ${props.goal === 'lose' ? '−' : '+'}${Number(props.rate)} kg`,
);
</script>

<template>
    <dl class="mt-3 text-sm tabular-nums">
        <div class="flex justify-between border-b border-dashed py-1.5">
            <dt>
                基礎代謝 BMR
                <span
                    :class="[
                        'ml-1 rounded-full px-2 py-0.5 text-[11px] font-semibold',
                        targets.bmr_overridden ? 'bg-brand-soft text-primary' : 'bg-muted text-muted-foreground',
                    ]"
                >
                    {{ targets.bmr_overridden ? '實測值' : '公式計算' }}
                </span>
            </dt>
            <dd>{{ formatNumber(targets.bmr) }}</dd>
        </div>
        <div class="flex justify-between border-b border-dashed py-1.5">
            <dt>
                × 日常活動 <span class="text-xs text-muted-foreground">{{ activityLabel }} {{ activityFactor }}</span>
            </dt>
            <dd>{{ formatNumber(targets.tdee) }}</dd>
        </div>
        <div class="flex justify-between border-b border-dashed py-1.5">
            <dt>
                目標調整 <span class="text-xs text-muted-foreground">{{ rateText }}</span>
            </dt>
            <dd>{{ formatSigned(targets.calorie_adjustment) }}</dd>
        </div>
        <div class="flex justify-between py-1.5 font-bold">
            <dt>每日熱量目標</dt>
            <dd>{{ formatNumber(targets.calorie_target) }} kcal</dd>
        </div>
    </dl>
</template>
