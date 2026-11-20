<script setup lang="ts">
import { confidenceLevel } from '@/composables/useConfidence';
import { computed } from 'vue';

const props = defineProps<{
    value: number;
}>();

const level = computed(() => confidenceLevel(props.value));
</script>

<template>
    <span
        :class="[
            'inline-flex h-5 shrink-0 items-center gap-[3px] rounded-full px-[7px] text-[11px] font-semibold',
            level.low ? 'bg-brand-soft text-primary' : 'bg-muted text-muted-foreground',
        ]"
        title="AI 信心度"
    >
        <span class="inline-flex h-2.5 items-end gap-[1.5px]" aria-hidden="true">
            <i
                v-for="(height, index) in ['h-1', 'h-[7px]', 'h-2.5']"
                :key="index"
                :class="['block w-[3px] rounded-[1px] bg-current', height, index < level.bars ? '' : 'opacity-30']"
            />
        </span>
        <span class="sr-only">信心度</span>{{ level.label }}
    </span>
</template>
