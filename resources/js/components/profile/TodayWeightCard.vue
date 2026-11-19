<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { shortDate } from '@/composables/useDateFormat';
import { useQuickActions } from '@/composables/useQuickActions';
import type { WeightLogItem } from '@/types/profile';
import { Plus, Weight } from 'lucide-vue-next';

defineProps<{
    currentWeight: WeightLogItem | null;
    weighedToday: boolean;
    today: string;
}>();

const { openWeightSheet } = useQuickActions();
</script>

<template>
    <section class="rounded-xl border bg-card p-4">
        <h2 class="mb-3 flex items-center gap-2 font-semibold"><Weight class="size-4" /> 體重</h2>
        <template v-if="weighedToday && currentWeight">
            <div class="text-4xl font-extrabold tabular-nums tracking-tight">
                {{ currentWeight.weight_kg }}<small class="ml-1 text-sm font-medium text-muted-foreground">kg</small>
            </div>
            <div class="text-sm text-muted-foreground">今天已記錄</div>
        </template>
        <template v-else>
            <div class="font-semibold">今天量體重了嗎？</div>
            <div v-if="currentWeight" class="text-sm text-muted-foreground">
                最近一次：{{ shortDate(currentWeight.measured_on, today) }} {{ currentWeight.weight_kg }} kg
            </div>
        </template>
        <Button :variant="weighedToday ? 'outline' : 'default'" size="lg" class="mt-3 h-11 w-full" @click="openWeightSheet">
            <Plus class="size-4" /> 記錄體重
        </Button>
    </section>
</template>
