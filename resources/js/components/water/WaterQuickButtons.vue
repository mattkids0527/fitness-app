<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useWaterLog } from '@/composables/useWaterLog';

defineProps<{
    amounts: number[];
}>();

const { processing, record } = useWaterLog();
</script>

<template>
    <div class="grid grid-cols-2 gap-2">
        <Button
            v-for="amount in amounts"
            :key="amount"
            type="button"
            variant="outline"
            size="lg"
            class="h-11 font-semibold text-sky-700 hover:text-sky-700 dark:text-sky-400 dark:hover:text-sky-400"
            :disabled="processing"
            @click="record(amount)"
        >
            +{{ amount }} ml
        </Button>
    </div>
</template>
