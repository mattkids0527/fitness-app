<script setup lang="ts" generic="T extends string">
import type { Option } from '@/types/profile';

defineProps<{
    options: Option<T>[];
}>();

const model = defineModel<T | null>({ required: true });
</script>

<template>
    <div class="grid gap-2" role="radiogroup">
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            role="radio"
            :aria-checked="model === option.value"
            :class="[
                'flex w-full items-center gap-3 rounded-xl border-[1.5px] px-3.5 py-3 text-left transition-colors',
                model === option.value ? 'border-primary bg-brand-soft' : 'border-input bg-card hover:bg-muted/50',
            ]"
            @click="model = option.value"
        >
            <div class="flex-1">
                <div class="font-semibold">{{ option.label }}</div>
                <div v-if="option.description" class="text-sm text-muted-foreground">{{ option.description }}</div>
            </div>
            <span :class="['size-5 shrink-0 rounded-full', model === option.value ? 'border-[6px] border-primary' : 'border-2 border-input']" />
        </button>
    </div>
</template>
