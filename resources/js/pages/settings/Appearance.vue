<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AppearanceTabs from '@/components/AppearanceTabs.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { type BreadcrumbItem } from '@/types';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: '外觀',
        href: '/settings/appearance',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="外觀" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall title="外觀" description="選擇淺色、深色或跟隨系統" />
                <AppearanceTabs />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
