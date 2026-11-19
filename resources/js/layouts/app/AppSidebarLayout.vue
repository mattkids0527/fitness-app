<script setup lang="ts">
import AppBottomNav from '@/components/AppBottomNav.vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import AppToast from '@/components/AppToast.vue';
import WeightLogSheet from '@/components/profile/WeightLogSheet.vue';
import QuickAddDialog from '@/components/QuickAddDialog.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="bg-page pb-[calc(env(safe-area-inset-bottom)+5rem)] md:pb-0">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <AppBottomNav />
        <QuickAddDialog />
        <WeightLogSheet />
        <AppToast />
    </AppShell>
</template>
