<script setup lang="ts">
import AppPage from '@/components/AppPage.vue';
import InputError from '@/components/InputError.vue';
import BmrTool from '@/components/profile/BmrTool.vue';
import ChoiceList from '@/components/profile/ChoiceList.vue';
import GoalField from '@/components/profile/GoalField.vue';
import TargetsCard from '@/components/profile/TargetsCard.vue';
import SegmentedControl from '@/components/SegmentedControl.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import UnitInput from '@/components/UnitInput.vue';
import { shortDate } from '@/composables/useDateFormat';
import { calculateTargets } from '@/composables/useEnergyTargets';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BmrMode, Profile, ProfileOptions, WeightLogItem } from '@/types/profile';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    profile: Profile;
    currentWeight: WeightLogItem | null;
    today: string;
    options: ProfileOptions;
}>();

const { show } = useToast();
const calc = props.options.calculation;

const form = useForm({
    gender: props.profile.gender,
    birth_date: props.profile.birth_date ?? '',
    height_cm: (props.profile.height_cm ?? '') as string | number,
    activity_level: props.profile.activity_level,
    goal: props.profile.goal,
    goal_rate_kg_per_week: (props.profile.goal_rate_kg_per_week ?? 0.5) as string | number,
    bmr_mode: (props.profile.bmr_override ? 'measured' : 'formula') as BmrMode,
    bmr_override: (props.profile.bmr_override ?? '') as string | number,
});

const targets = computed(() => calculateTargets(form, props.currentWeight?.weight_kg ?? null, props.today, calc));
const activity = computed(() => props.options.activity_levels.find((o) => o.value === form.activity_level));
const maxBirthDate = computed(() => {
    const [y, m, d] = props.today.split('-');

    return `${Number(y) - calc.min_age}-${m}-${d}`;
});
const bmrNote = computed(() =>
    props.currentWeight
        ? `以最新體重 ${props.currentWeight.weight_kg} kg（${shortDate(props.currentWeight.measured_on, props.today)}）計算；體重請到「體重紀錄」新增。`
        : undefined,
);

/**
 * 儲存個人資料；維持目標不送每週速度，公式模式不送 BMR 實測值。
 */
function submit() {
    form.transform((data) => ({
        gender: data.gender,
        birth_date: data.birth_date,
        height_cm: Number(data.height_cm),
        activity_level: data.activity_level,
        goal: data.goal,
        goal_rate_kg_per_week: data.goal === 'maintain' ? null : Number(data.goal_rate_kg_per_week),
        bmr_override: data.bmr_mode === 'measured' ? Number(data.bmr_override) : null,
    })).put(route('fitness.profile.update'), {
        onSuccess: () => show('已儲存個人資料'),
    });
}
</script>

<template>
    <Head title="編輯個人資料" />
    <AppLayout
        :breadcrumbs="[
            { title: '我的', href: route('fitness.profile.show') },
            { title: '編輯個人資料', href: route('fitness.profile.edit') },
        ]"
    >
        <AppPage title="編輯個人資料" :back-href="route('fitness.profile.show')" narrow>
            <form class="space-y-3" @submit.prevent="submit">
                <section class="space-y-5 rounded-xl border bg-card p-4">
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
                        <UnitInput id="height_cm" v-model="form.height_cm" unit="cm" :invalid="!!form.errors.height_cm" />
                        <InputError class="mt-1.5" :message="form.errors.height_cm" />
                    </div>
                </section>

                <section class="rounded-xl border bg-card p-4">
                    <span class="mb-2 block text-sm font-semibold">日常活動量（不含運動）</span>
                    <ChoiceList v-model="form.activity_level" :options="options.activity_levels" />
                    <InputError class="mt-1.5" :message="form.errors.activity_level" />
                </section>

                <section class="rounded-xl border bg-card p-4">
                    <span class="mb-2 block text-sm font-semibold">目標</span>
                    <GoalField
                        v-model:goal="form.goal"
                        v-model:rate="form.goal_rate_kg_per_week"
                        :goals="options.goals"
                        :ranges="options.goal_rate"
                        :kcal-per-kg="calc.kcal_per_kg"
                        :goal-error="form.errors.goal"
                        :rate-error="form.errors.goal_rate_kg_per_week"
                    />
                </section>

                <BmrTool
                    v-model:mode="form.bmr_mode"
                    v-model:override="form.bmr_override"
                    :targets="targets"
                    :gender="form.gender"
                    :height-cm="form.height_cm"
                    :error="form.errors.bmr_override"
                    :note="bmrNote"
                />

                <TargetsCard
                    title="儲存後的每日目標"
                    :targets="targets"
                    :gender="form.gender"
                    :goal="form.goal"
                    :rate="form.goal === 'maintain' ? null : Number(form.goal_rate_kg_per_week)"
                    :activity-label="activity?.label ?? ''"
                    :activity-factor="form.activity_level ? calc.activity_factors[form.activity_level] : 0"
                    :protein-per-kg="form.goal ? calc.protein_g_per_kg[form.goal] : 0"
                />

                <div
                    class="sticky bottom-[calc(env(safe-area-inset-bottom)+4.75rem)] z-10 -mx-4 flex gap-2 border-t bg-page/95 px-4 py-3 backdrop-blur md:static md:mx-0 md:justify-end md:border-0 md:bg-transparent md:px-0"
                >
                    <Button variant="outline" size="lg" class="h-11" as-child>
                        <Link :href="route('fitness.profile.show')">取消</Link>
                    </Button>
                    <Button type="submit" size="lg" class="h-11 flex-1 md:flex-none md:px-10" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                        儲存
                    </Button>
                </div>
            </form>
        </AppPage>
    </AppLayout>
</template>
