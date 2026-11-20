<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CalendarClock, GitCommitHorizontal, Tag } from 'lucide-vue-next';
import { computed } from 'vue';

import HeadingSmall from '@/components/HeadingSmall.vue';
import { type BreadcrumbItem } from '@/types';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

interface AppVersion {
    version: string;
    commit: string | null;
    deployed_at: string | null;
}

const props = defineProps<{
    version: AppVersion | null;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: '版本',
        href: '/settings/version',
    },
];

// 沒有版本資訊（本地開發或未給版號的部署）時，版本顯示「未標示版本」，其他欄位顯示「—」
const rows = computed(() => [
    { label: '版本', value: props.version?.version ?? '未標示版本', icon: Tag, mono: false },
    { label: '部署時間', value: props.version?.deployed_at ?? '—', icon: CalendarClock, mono: false },
    { label: 'Commit', value: props.version?.commit ?? '—', icon: GitCommitHorizontal, mono: true },
]);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="版本" />

        <SettingsLayout>
            <div class="space-y-5">
                <HeadingSmall title="版本" description="目前使用的 App 版本" />

                <dl class="divide-y overflow-hidden rounded-xl border bg-card">
                    <div v-for="row in rows" :key="row.label" class="flex items-center gap-3 px-4 py-3.5">
                        <component :is="row.icon" class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                        <dt class="text-sm text-muted-foreground">{{ row.label }}</dt>
                        <dd :class="['ml-auto text-sm font-medium', { 'font-mono': row.mono }]">{{ row.value }}</dd>
                    </div>
                </dl>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
