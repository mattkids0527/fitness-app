<script setup lang="ts">
import { formatNumber } from '@/composables/useEnergyTargets';
import type { EnergyTargets, Gender, Goal } from '@/types/profile';
import { Drumstick, Flame } from 'lucide-vue-next';
import LowCalorieWarning from './LowCalorieWarning.vue';
import TargetsBreakdown from './TargetsBreakdown.vue';

defineProps<{
    title: string;
    targets: EnergyTargets | null;
    gender: Gender | null;
    goal: Goal | null;
    rate: number | null;
    activityLabel: string;
    activityFactor: number;
    proteinPerKg: number;
}>();
</script>

<template>
    <section class="rounded-xl border bg-card p-4">
        <h3 class="mb-3 flex items-center gap-2 font-semibold"><Flame class="size-4" /> {{ title }}</h3>
        <template v-if="targets && goal">
            <div class="text-4xl font-extrabold tabular-nums tracking-tight">
                {{ formatNumber(targets.calorie_target) }}<small class="ml-1 text-sm font-medium text-muted-foreground">kcal / 天</small>
            </div>
            <TargetsBreakdown :targets="targets" :activity-label="activityLabel" :activity-factor="activityFactor" :goal="goal" :rate="rate" />
            <div class="mt-3 rounded-lg bg-muted p-3">
                <div class="flex items-center gap-1 text-xs text-muted-foreground"><Drumstick class="size-3.5" /> 蛋白質目標</div>
                <div class="mt-1 text-xl font-bold tabular-nums">
                    {{ targets.protein_target_g }}<small class="ml-0.5 text-xs font-medium text-muted-foreground">g / 天</small>
                </div>
                <div class="text-xs text-muted-foreground">體重 {{ targets.weight_kg }} kg × {{ proteinPerKg }} g</div>
            </div>
            <LowCalorieWarning :targets="targets" :gender="gender" />
        </template>
        <p v-else class="text-sm text-muted-foreground">資料填寫完整後顯示。</p>
    </section>
</template>
