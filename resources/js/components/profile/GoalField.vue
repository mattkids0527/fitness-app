<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { dailyAdjustment, formatSigned } from '@/composables/useEnergyTargets';
import type { Goal, Option, RateRange } from '@/types/profile';
import { computed, watch } from 'vue';

const props = defineProps<{
    goals: Option<Goal>[];
    ranges: Record<'lose' | 'gain', RateRange>;
    kcalPerKg: number;
    goalError?: string;
    rateError?: string;
}>();

const goal = defineModel<Goal | null>('goal', { required: true });
const rate = defineModel<string | number>('rate', { required: true });

const range = computed(() => (goal.value && goal.value !== 'maintain' ? props.ranges[goal.value] : null));
const adjustment = computed(() => dailyAdjustment(goal.value, Number(rate.value), props.kcalPerKg));

// 切換目標時，把每週速度夾回新目標允許的範圍
watch(goal, () => {
    if (!range.value) {
        return;
    }

    const value = Number(rate.value);

    if (!(value >= range.value.min && value <= range.value.max)) {
        rate.value = Math.min(0.5, range.value.max);
    }
});
</script>

<template>
    <div>
        <div class="grid grid-cols-3 gap-2" role="radiogroup">
            <button
                v-for="option in goals"
                :key="option.value"
                type="button"
                role="radio"
                :aria-checked="goal === option.value"
                :class="[
                    'flex flex-col items-center gap-1 rounded-xl border-[1.5px] px-1.5 py-3.5 text-center transition-colors',
                    goal === option.value ? 'border-primary bg-brand-soft' : 'border-input bg-card hover:bg-muted/50',
                ]"
                @click="goal = option.value"
            >
                <span class="font-semibold">{{ option.label }}</span>
                <span class="text-xs text-muted-foreground">{{ option.description }}</span>
            </button>
        </div>
        <InputError class="mt-1.5" :message="goalError" />

        <div v-if="range" class="mt-5">
            <div class="flex items-center justify-between">
                <span class="text-sm font-semibold">每週目標速度</span>
                <b>{{ Number(rate).toFixed(1) }} kg</b>
            </div>
            <div class="mb-2 mt-1 text-sm text-muted-foreground">每日熱量 {{ formatSigned(adjustment) }} kcal</div>
            <input v-model="rate" type="range" :min="range.min" :max="range.max" step="0.1" class="w-full accent-primary" aria-label="每週目標速度" />
            <div class="flex justify-between text-xs text-muted-foreground">
                <span>{{ range.min }} kg</span>
                <span>{{ range.max }} kg</span>
            </div>
            <p class="mt-1.5 text-xs text-muted-foreground">
                {{ goal === 'lose' ? '一般建議每週減重約體重的 0.5%～1%。' : '增肌建議每週 0.25 kg 左右，太快容易增加脂肪。' }}
            </p>
            <InputError class="mt-1.5" :message="rateError" />
        </div>
    </div>
</template>
