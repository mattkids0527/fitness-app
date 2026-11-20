<script setup lang="ts">
import AppPage from '@/components/AppPage.vue';
import MissingNutrientsNotice from '@/components/meal/MissingNutrientsNotice.vue';
import TodayMealsCard from '@/components/meal/TodayMealsCard.vue';
import TodayTargetsCard from '@/components/profile/TodayTargetsCard.vue';
import TodayWeightCard from '@/components/profile/TodayWeightCard.vue';
import TodayWaterCard from '@/components/water/TodayWaterCard.vue';
import { longDate } from '@/composables/useDateFormat';
import AppLayout from '@/layouts/AppLayout.vue';
import type { DailySummarySections } from '@/types/daily-summary';
import { Head } from '@inertiajs/vue3';
import { Dumbbell } from 'lucide-vue-next';

defineProps<{
    date: string;
    sections: DailySummarySections;
}>();
</script>

<template>
    <Head title="今日" />
    <AppLayout :breadcrumbs="[{ title: '今日', href: route('fitness.daily_summary.show') }]">
        <AppPage title="今日" :description="longDate(date)">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 md:items-start">
                <div class="space-y-3">
                    <TodayTargetsCard
                        v-if="sections.profile?.targets"
                        :targets="sections.profile.targets"
                        :consumed-kcal="sections.meal?.totals.kcal"
                        :consumed-protein-g="sections.meal?.totals.protein_g"
                    >
                        <MissingNutrientsNotice v-if="sections.meal?.has_missing_nutrients" />
                    </TodayTargetsCard>
                    <TodayMealsCard v-if="sections.meal" :meals="sections.meal.meals" :meal-types="sections.meal.meal_types" />
                </div>

                <div class="space-y-3">
                    <TodayWaterCard v-if="sections.water" :water="sections.water" />
                    <TodayWeightCard
                        v-if="sections.profile"
                        :current-weight="sections.profile.current_weight"
                        :weighed-today="sections.profile.weighed_today"
                        :today="date"
                    />

                    <div>
                        <h2 class="mx-1 mb-1.5 text-sm text-muted-foreground">即將推出</h2>
                        <div class="flex items-center gap-3 rounded-xl border-[1.5px] border-dashed border-input p-3.5 text-sm text-muted-foreground">
                            <span class="grid size-10 place-items-center rounded-lg bg-muted"><Dumbbell class="size-5" /></span>
                            <div>
                                <div class="font-semibold">運動</div>
                                <div>有氧與重訓紀錄、消耗熱量</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppPage>
    </AppLayout>
</template>
