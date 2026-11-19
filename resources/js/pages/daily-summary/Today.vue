<script setup lang="ts">
import AppPage from '@/components/AppPage.vue';
import TodayTargetsCard from '@/components/profile/TodayTargetsCard.vue';
import TodayWeightCard from '@/components/profile/TodayWeightCard.vue';
import { longDate } from '@/composables/useDateFormat';
import AppLayout from '@/layouts/AppLayout.vue';
import type { DailySummarySections } from '@/types/daily-summary';
import { Head } from '@inertiajs/vue3';
import { Droplet, Dumbbell, Utensils } from 'lucide-vue-next';

defineProps<{
    date: string;
    sections: DailySummarySections;
}>();

// 之後由飲食、喝水、健身模組各自加入區塊
const comingSoon = [
    { title: '今日餐點', description: '拍照或手動記錄每一餐', icon: Utensils },
    { title: '喝水', description: '快速記錄與每日建議量', icon: Droplet },
    { title: '運動', description: '有氧與重訓紀錄、消耗熱量', icon: Dumbbell },
];
</script>

<template>
    <Head title="今日" />
    <AppLayout :breadcrumbs="[{ title: '今日', href: route('fitness.daily_summary.show') }]">
        <AppPage title="今日" :description="longDate(date)">
            <div v-if="sections.profile" class="grid gap-3 md:grid-cols-2 md:items-start">
                <TodayTargetsCard v-if="sections.profile.targets" :targets="sections.profile.targets" />
                <TodayWeightCard :current-weight="sections.profile.current_weight" :weighed-today="sections.profile.weighed_today" :today="date" />
            </div>

            <h2 class="mx-1 mb-1.5 mt-5 text-sm text-muted-foreground">即將推出</h2>
            <div class="space-y-2.5">
                <div
                    v-for="item in comingSoon"
                    :key="item.title"
                    class="flex items-center gap-3 rounded-xl border-[1.5px] border-dashed border-input p-3.5 text-sm text-muted-foreground"
                >
                    <span class="grid size-10 place-items-center rounded-lg bg-muted"><component :is="item.icon" class="size-5" /></span>
                    <div>
                        <div class="font-semibold">{{ item.title }}</div>
                        <div>{{ item.description }}</div>
                    </div>
                </div>
            </div>
        </AppPage>
    </AppLayout>
</template>
