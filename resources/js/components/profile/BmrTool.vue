<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SegmentedControl from '@/components/SegmentedControl.vue';
import UnitInput from '@/components/UnitInput.vue';
import { formatNumber } from '@/composables/useEnergyTargets';
import type { BmrMode, EnergyTargets, Gender } from '@/types/profile';
import { Calculator } from 'lucide-vue-next';

defineProps<{
    targets: EnergyTargets | null;
    gender: Gender | null;
    heightCm: number | string;
    error?: string;
    note?: string;
}>();

const mode = defineModel<BmrMode>('mode', { required: true });
const override = defineModel<string | number>('override', { required: true });

const modeOptions: { value: BmrMode; label: string }[] = [
    { value: 'formula', label: '公式計算' },
    { value: 'measured', label: '填入實測值' },
];
</script>

<template>
    <section class="rounded-xl border bg-card p-4">
        <h3 class="mb-3 flex items-center gap-2 font-semibold"><Calculator class="size-4" /> 基礎代謝 BMR</h3>
        <SegmentedControl v-model="mode" :options="modeOptions" />

        <div v-if="mode === 'measured'" class="mt-4">
            <UnitInput v-model="override" unit="kcal" placeholder="例如 1650" step="1" :invalid="!!error" aria-label="BMR 實測值" />
            <InputError class="mt-1.5" :message="error" />
            <p class="mt-1.5 text-xs text-muted-foreground">可填 InBody 等量測結果（500～5000）。</p>
            <p v-if="targets" class="mt-2 rounded-lg bg-muted px-3 py-2 text-xs text-muted-foreground">
                公式計算值為 {{ formatNumber(targets.bmr_calculated) }} kcal，儲存後改用你填的實測值。
            </p>
        </div>

        <div v-else-if="targets" class="mt-4">
            <div class="text-4xl font-extrabold tabular-nums tracking-tight">
                {{ formatNumber(targets.bmr_calculated) }}<small class="ml-1 text-sm font-medium text-muted-foreground">kcal / 天</small>
            </div>
            <details class="mt-2">
                <summary class="cursor-pointer text-sm text-primary">計算方式</summary>
                <div class="mt-2 rounded-lg bg-muted px-3 py-2 text-xs tabular-nums leading-relaxed text-muted-foreground">
                    Mifflin-St Jeor 公式（{{ gender === 'female' ? '女' : '男' }}）<br />
                    10 × {{ targets.weight_kg }} kg + 6.25 × {{ Number(heightCm) }} cm − 5 × {{ targets.age }} 歲
                    {{ gender === 'female' ? '− 161' : '+ 5' }} = {{ formatNumber(targets.bmr_calculated) }}
                </div>
            </details>
        </div>

        <p v-else class="mt-4 text-sm text-muted-foreground">填完性別、出生日期、身高、體重後自動計算。</p>
        <p v-if="note" class="mt-2 text-xs text-muted-foreground">{{ note }}</p>
    </section>
</template>
