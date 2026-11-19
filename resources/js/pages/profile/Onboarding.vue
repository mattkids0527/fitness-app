<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import InputError from '@/components/InputError.vue';
import BmrTool from '@/components/profile/BmrTool.vue';
import ChoiceList from '@/components/profile/ChoiceList.vue';
import GoalField from '@/components/profile/GoalField.vue';
import TargetsCard from '@/components/profile/TargetsCard.vue';
import SegmentedControl from '@/components/SegmentedControl.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import UnitInput from '@/components/UnitInput.vue';
import { ageOn, calculateTargets } from '@/composables/useEnergyTargets';
import type { ActivityLevel, BmrMode, Gender, Goal, ProfileOptions } from '@/types/profile';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Info, LoaderCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    options: ProfileOptions;
    today: string;
}>();

const form = useForm({
    gender: null as Gender | null,
    birth_date: '',
    height_cm: '' as string | number,
    weight_kg: '' as string | number,
    activity_level: null as ActivityLevel | null,
    goal: null as Goal | null,
    goal_rate_kg_per_week: 0.5 as string | number,
    bmr_mode: 'formula' as BmrMode,
    bmr_override: '' as string | number,
});

const step = ref(1);
const totalSteps = 4;

type Field = 'gender' | 'birth_date' | 'height_cm' | 'weight_kg' | 'activity_level' | 'goal' | 'goal_rate_kg_per_week' | 'bmr_override';

// 每一步負責的欄位：驗證失敗時跳回第一個有錯的步驟
const stepFields: Record<number, Field[]> = {
    1: ['gender', 'birth_date', 'height_cm'],
    2: ['weight_kg', 'activity_level'],
    3: ['goal', 'goal_rate_kg_per_week'],
    4: ['bmr_override'],
};

const steps: Record<number, { title: string; description: string }> = {
    1: { title: '先認識一下你', description: '這些資料用來計算基礎代謝（BMR）與每日熱量目標，之後可以在「我的」修改。' },
    2: { title: '體重與日常活動', description: '體重會成為你的第一筆體重紀錄。' },
    3: { title: '你的目標', description: '決定每日熱量要減少或增加多少，以及蛋白質目標。' },
    4: { title: '確認每日目標', description: 'BMR 預設以公式計算；若有 InBody 等實測值，可以改用實測值。' },
};

const calc = props.options.calculation;
const targets = computed(() => calculateTargets(form, form.weight_kg, props.today, calc));
const activity = computed(() => props.options.activity_levels.find((o) => o.value === form.activity_level));

// 出生日期上限：需年滿最低年齡
const maxBirthDate = computed(() => {
    const [y, m, d] = props.today.split('-');

    return `${Number(y) - calc.min_age}-${m}-${d}`;
});

/**
 * 前端先檢查目前這一步的欄位，訊息與後端驗證一致；最後仍以後端驗證為準。
 */
function validateStep(current: number): boolean {
    const errors: Record<string, string> = {};
    const age = ageOn(form.birth_date, props.today);
    const height = Number(form.height_cm);
    const weight = Number(form.weight_kg);

    if (current === 1) {
        if (!form.gender) errors.gender = '請選擇性別。';
        if (!form.birth_date) errors.birth_date = '請填寫出生日期。';
        else if (age === null || age < calc.min_age) errors.birth_date = `需年滿 ${calc.min_age} 歲。`;
        if (!(height >= 100 && height <= 250)) errors.height_cm = '身高需介於 100 到 250 公分。';
    }

    if (current === 2) {
        if (!(weight >= 20 && weight <= 300)) errors.weight_kg = '體重需介於 20 到 300 公斤。';
        if (!form.activity_level) errors.activity_level = '請選擇日常活動量。';
    }

    if (current === 3 && !form.goal) {
        errors.goal = '請選擇目標。';
    }

    if (current === 4 && form.bmr_mode === 'measured') {
        const value = Number(form.bmr_override);
        if (!Number.isInteger(value) || value < 500 || value > 5000) errors.bmr_override = 'BMR 實測值需為 500 到 5000 的整數。';
    }

    form.clearErrors(...stepFields[current]);
    form.setError(errors);

    return Object.keys(errors).length === 0;
}

/**
 * 往下一步；最後一步送出。
 */
function next() {
    if (!validateStep(step.value)) {
        return;
    }

    if (step.value < totalSteps) {
        step.value++;
        window.scrollTo(0, 0);

        return;
    }

    form.transform((data) => ({
        gender: data.gender,
        birth_date: data.birth_date,
        height_cm: Number(data.height_cm),
        weight_kg: Number(data.weight_kg),
        activity_level: data.activity_level,
        goal: data.goal,
        goal_rate_kg_per_week: data.goal === 'maintain' ? null : Number(data.goal_rate_kg_per_week),
        bmr_override: data.bmr_mode === 'measured' ? Number(data.bmr_override) : null,
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
    })).post(route('fitness.profile.onboarding.store'), {
        onError: (errors) => {
            const firstStep = Object.keys(stepFields)
                .map(Number)
                .find((s) => stepFields[s].some((field) => field in errors));
            step.value = firstStep ?? 1;
        },
    });
}
</script>

<template>
    <Head title="首次設定" />
    <div class="min-h-dvh bg-page md:py-10">
        <div class="mx-auto flex max-w-xl items-center justify-between px-4 pt-4 md:px-0 md:pt-0">
            <div class="flex items-center"><AppLogo /></div>
            <Link :href="route('logout')" method="post" as="button" class="text-sm text-muted-foreground hover:text-foreground">登出</Link>
        </div>

        <main class="mx-auto max-w-xl px-4 pb-32 pt-5 md:mt-4 md:rounded-2xl md:border md:bg-card md:p-8 md:pb-8">
            <div class="mb-5 flex gap-1.5" aria-hidden="true">
                <i v-for="i in totalSteps" :key="i" :class="['h-1 flex-1 rounded-full', i <= step ? 'bg-primary' : 'bg-input']" />
            </div>
            <div class="text-sm font-semibold text-primary">步驟 {{ step }} / {{ totalSteps }}</div>
            <h1 class="mb-1.5 mt-1 text-2xl font-extrabold">{{ steps[step].title }}</h1>
            <p class="mb-6 text-sm leading-relaxed text-muted-foreground">{{ steps[step].description }}</p>

            <div v-if="step === 1" class="space-y-5">
                <div>
                    <span class="mb-2 block text-sm font-semibold">性別</span>
                    <SegmentedControl v-model="form.gender" :options="options.genders" />
                    <InputError class="mt-1.5" :message="form.errors.gender" />
                </div>
                <div>
                    <Label for="birth_date" class="mb-2 block font-semibold">出生日期</Label>
                    <UnitInput
                        id="birth_date"
                        v-model="form.birth_date"
                        type="date"
                        min="1900-01-01"
                        :max="maxBirthDate"
                        :invalid="!!form.errors.birth_date"
                    />
                    <InputError class="mt-1.5" :message="form.errors.birth_date" />
                </div>
                <div>
                    <Label for="height_cm" class="mb-2 block font-semibold">身高</Label>
                    <UnitInput id="height_cm" v-model="form.height_cm" unit="cm" placeholder="170" :invalid="!!form.errors.height_cm" />
                    <InputError class="mt-1.5" :message="form.errors.height_cm" />
                </div>
            </div>

            <div v-else-if="step === 2" class="space-y-5">
                <div>
                    <Label for="weight_kg" class="mb-2 block font-semibold">目前體重</Label>
                    <UnitInput id="weight_kg" v-model="form.weight_kg" unit="kg" placeholder="65.0" :invalid="!!form.errors.weight_kg" />
                    <InputError class="mt-1.5" :message="form.errors.weight_kg" />
                </div>
                <div>
                    <span class="mb-2 block text-sm font-semibold">日常活動量</span>
                    <ChoiceList v-model="form.activity_level" :options="options.activity_levels" />
                    <InputError class="mt-1.5" :message="form.errors.activity_level" />
                    <div class="mt-3 flex gap-2.5 rounded-lg bg-muted p-3 text-sm leading-relaxed text-muted-foreground">
                        <Info class="mt-0.5 size-4 shrink-0" />
                        <p>只算工作與生活的活動量，<b>不含運動</b>。運動消耗之後由健身紀錄另外計算，避免重複。</p>
                    </div>
                </div>
            </div>

            <div v-else-if="step === 3">
                <GoalField
                    v-model:goal="form.goal"
                    v-model:rate="form.goal_rate_kg_per_week"
                    :goals="options.goals"
                    :ranges="options.goal_rate"
                    :kcal-per-kg="calc.kcal_per_kg"
                    :goal-error="form.errors.goal"
                    :rate-error="form.errors.goal_rate_kg_per_week"
                />
            </div>

            <div v-else class="space-y-3">
                <BmrTool
                    v-model:mode="form.bmr_mode"
                    v-model:override="form.bmr_override"
                    :targets="targets"
                    :gender="form.gender"
                    :height-cm="form.height_cm"
                    :error="form.errors.bmr_override"
                />
                <TargetsCard
                    title="你的每日目標"
                    :targets="targets"
                    :gender="form.gender"
                    :goal="form.goal"
                    :rate="form.goal === 'maintain' ? null : Number(form.goal_rate_kg_per_week)"
                    :activity-label="activity?.label ?? ''"
                    :activity-factor="form.activity_level ? calc.activity_factors[form.activity_level] : 0"
                    :protein-per-kg="form.goal ? calc.protein_g_per_kg[form.goal] : 0"
                />
            </div>

            <div
                class="fixed inset-x-0 bottom-0 flex gap-2 border-t bg-background px-4 pb-[calc(env(safe-area-inset-bottom)+1rem)] pt-3 md:static md:mt-8 md:justify-end md:border-0 md:bg-transparent md:p-0"
            >
                <Button v-if="step > 1" type="button" variant="outline" size="lg" class="h-11" @click="step--">上一步</Button>
                <Button type="button" size="lg" class="h-11 flex-1 md:flex-none md:px-10" :disabled="form.processing" @click="next">
                    <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                    {{ step === totalSteps ? '完成設定' : '下一步' }}
                </Button>
            </div>
        </main>
    </div>
</template>
