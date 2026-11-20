<script setup lang="ts">
import AppPage from '@/components/AppPage.vue';
import LowCalorieWarning from '@/components/profile/LowCalorieWarning.vue';
import TargetsBreakdown from '@/components/profile/TargetsBreakdown.vue';
import WeightDelta from '@/components/profile/WeightDelta.vue';
import { Button } from '@/components/ui/button';
import { localToday, shortDate } from '@/composables/useDateFormat';
import { formatNumber } from '@/composables/useEnergyTargets';
import { useInitials } from '@/composables/useInitials';
import { useQuickActions } from '@/composables/useQuickActions';
import AppLayout from '@/layouts/AppLayout.vue';
import type { SharedData } from '@/types';
import type { EnergyTargets, Profile, ProfileOptions, WeightLogItem } from '@/types/profile';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ChevronRight, Flame, Link as LinkIcon, Lock, LogOut, Plus, Sun, Weight } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    profile: Profile;
    targets: EnergyTargets | null;
    currentWeight: WeightLogItem | null;
    options: ProfileOptions;
}>();

const page = usePage<SharedData>();
const user = computed(() => page.props.auth.user);
const { getInitials } = useInitials();
const { openWeightSheet } = useQuickActions();
const today = localToday();

const labelOf = <T extends string>(list: { value: T; label: string }[], value: T | null) => list.find((o) => o.value === value)?.label ?? '—';

const age = computed(() => props.targets?.age);
const goalText = computed(() => {
    const goal = labelOf(props.options.goals, props.profile.goal);

    return props.profile.goal === 'maintain' || !props.profile.goal_rate_kg_per_week
        ? goal
        : `${goal} · 每週 ${props.profile.goal_rate_kg_per_week} kg`;
});

const rows = computed(() => [
    { label: '性別', value: labelOf(props.options.genders, props.profile.gender) },
    {
        label: '出生日期',
        value: props.profile.birth_date
            ? `${props.profile.birth_date.replaceAll('-', '/')}${age.value !== undefined ? `（${age.value} 歲）` : ''}`
            : '—',
    },
    { label: '身高', value: props.profile.height_cm ? `${props.profile.height_cm} cm` : '—' },
    { label: '日常活動量', value: labelOf(props.options.activity_levels, props.profile.activity_level) },
    { label: '目標', value: goalText.value },
    {
        label: '基礎代謝 BMR',
        value: props.targets ? `${formatNumber(props.targets.bmr)} kcal${props.targets.bmr_overridden ? '（實測）' : ''}` : '—',
    },
]);

const activityLabel = computed(() => labelOf(props.options.activity_levels, props.profile.activity_level));
const activityFactor = computed(() => (props.profile.activity_level ? props.options.calculation.activity_factors[props.profile.activity_level] : 0));
</script>

<template>
    <Head title="我的" />
    <AppLayout :breadcrumbs="[{ title: '我的', href: route('fitness.profile.show') }]">
        <AppPage>
            <div class="flex items-center gap-3.5 px-1 pb-1 pt-2">
                <div class="grid size-14 shrink-0 place-items-center rounded-full bg-brand-soft text-xl font-bold text-primary">
                    {{ getInitials(user.name) }}
                </div>
                <div class="min-w-0">
                    <div class="truncate text-lg font-bold">{{ user.name }}</div>
                    <div class="truncate text-sm text-muted-foreground">{{ user.email }}</div>
                </div>
            </div>

            <div class="mt-3 grid gap-3 md:grid-cols-2 md:items-start">
                <section class="rounded-xl border bg-card p-4">
                    <h2 class="mb-3 flex items-center gap-2 font-semibold">
                        <Flame class="size-4" /> 每日目標
                        <Link :href="route('fitness.profile.edit')" class="ml-auto flex items-center text-sm font-medium text-primary">
                            調整 <ChevronRight class="size-4" />
                        </Link>
                    </h2>
                    <template v-if="targets && profile.goal">
                        <div class="grid grid-cols-2 gap-2.5">
                            <div class="rounded-lg bg-muted p-3">
                                <div class="text-xs text-muted-foreground">熱量</div>
                                <div class="mt-1 text-xl font-bold tabular-nums">
                                    {{ formatNumber(targets.calorie_target)
                                    }}<small class="ml-0.5 text-xs font-medium text-muted-foreground">kcal</small>
                                </div>
                            </div>
                            <div class="rounded-lg bg-muted p-3">
                                <div class="text-xs text-muted-foreground">蛋白質</div>
                                <div class="mt-1 text-xl font-bold tabular-nums">
                                    {{ targets.protein_target_g }}<small class="ml-0.5 text-xs font-medium text-muted-foreground">g</small>
                                </div>
                            </div>
                        </div>
                        <details class="mt-3">
                            <summary class="cursor-pointer text-sm text-muted-foreground">怎麼算的？</summary>
                            <TargetsBreakdown
                                :targets="targets"
                                :activity-label="activityLabel"
                                :activity-factor="activityFactor"
                                :goal="profile.goal"
                                :rate="profile.goal_rate_kg_per_week"
                            />
                        </details>
                        <LowCalorieWarning :targets="targets" :gender="profile.gender" />
                    </template>
                </section>

                <section class="rounded-xl border bg-card p-4">
                    <h2 class="mb-3 flex items-center gap-2 font-semibold">
                        <Weight class="size-4" /> 體重
                        <Link :href="route('fitness.profile.weight_logs.index')" class="ml-auto flex items-center text-sm font-medium text-primary">
                            全部紀錄 <ChevronRight class="size-4" />
                        </Link>
                    </h2>
                    <template v-if="currentWeight">
                        <div class="text-4xl font-extrabold tabular-nums tracking-tight">
                            {{ currentWeight.weight_kg }}<small class="ml-1 text-sm font-medium text-muted-foreground">kg</small>
                        </div>
                        <div class="mt-0.5 text-sm text-muted-foreground">
                            {{ shortDate(currentWeight.measured_on, today) }}
                            <template v-if="currentWeight.delta_kg !== null"> · 較前次 <WeightDelta :delta="currentWeight.delta_kg" /> kg</template>
                            <template v-else> · 第一筆紀錄</template>
                        </div>
                    </template>
                    <Button variant="outline" size="lg" class="mt-3 h-11 w-full" @click="openWeightSheet"><Plus class="size-4" /> 記錄體重</Button>
                </section>
            </div>

            <h2 class="mx-1 mb-1.5 mt-5 text-sm text-muted-foreground">個人資料</h2>
            <div class="overflow-hidden rounded-xl border bg-card">
                <Link
                    v-for="row in rows"
                    :key="row.label"
                    :href="route('fitness.profile.edit')"
                    class="flex min-h-[50px] items-center gap-3 border-b px-3.5 py-2.5 last:border-b-0 hover:bg-muted/50"
                >
                    <span class="flex-1">{{ row.label }}</span>
                    <span class="text-right text-sm text-muted-foreground">{{ row.value }}</span>
                    <ChevronRight class="size-4 text-muted-foreground" />
                </Link>
            </div>

            <h2 class="mx-1 mb-1.5 mt-5 text-sm text-muted-foreground">設定</h2>
            <div class="overflow-hidden rounded-xl border bg-card">
                <Link
                    :href="route('fitness.social_login.connected_accounts.index')"
                    class="flex min-h-[50px] items-center gap-3 border-b px-3.5 py-2.5 hover:bg-muted/50"
                >
                    <LinkIcon class="size-4" /><span class="flex-1">已連結帳號</span><ChevronRight class="size-4 text-muted-foreground" />
                </Link>
                <Link :href="route('appearance')" class="flex min-h-[50px] items-center gap-3 border-b px-3.5 py-2.5 hover:bg-muted/50">
                    <Sun class="size-4" /><span class="flex-1">外觀</span><ChevronRight class="size-4 text-muted-foreground" />
                </Link>
                <Link :href="route('profile.edit')" class="flex min-h-[50px] items-center gap-3 border-b px-3.5 py-2.5 hover:bg-muted/50">
                    <Lock class="size-4" /><span class="flex-1">帳號與密碼</span><ChevronRight class="size-4 text-muted-foreground" />
                </Link>
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="flex min-h-[50px] w-full items-center gap-3 px-3.5 py-2.5 text-left text-destructive hover:bg-muted/50"
                >
                    <LogOut class="size-4" /><span class="flex-1">登出</span>
                </Link>
            </div>
        </AppPage>
    </AppLayout>
</template>
