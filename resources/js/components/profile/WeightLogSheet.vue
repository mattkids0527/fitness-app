<script setup lang="ts">
import ResponsiveDialog from '@/components/ResponsiveDialog.vue';
import { useQuickActions } from '@/composables/useQuickActions';
import WeightLogForm from './WeightLogForm.vue';

const { weightSheetOpen } = useQuickActions();
</script>

<template>
    <ResponsiveDialog v-model:open="weightSheetOpen" title="記錄體重" description="建議每天固定時間量，例如起床如廁後。">
        <WeightLogForm @saved="weightSheetOpen = false" />
    </ResponsiveDialog>
</template>
