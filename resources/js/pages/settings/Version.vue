<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CalendarDays, Tag } from 'lucide-vue-next';
import { computed } from 'vue';

import HeadingSmall from '@/components/HeadingSmall.vue';
import { type BreadcrumbItem } from '@/types';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

interface AppVersion {
    version: string;
    updated_at: string | null;
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

// 沒有版本資訊（本地開發或未給版號的部署）時，版本顯示「未標示版本」，更新日期顯示「—」
const rows = computed(() => [
    { label: '版本', value: props.version?.version ?? '未標示版本', icon: Tag },
    { label: '更新日期', value: props.version?.updated_at ?? '—', icon: CalendarDays },
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
                        <dd class="ml-auto text-sm font-medium">{{ row.value }}</dd>
                    </div>
                </dl>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
