<script setup lang="ts">
// aria-label 等屬性要落在 input 上，不是外框
defineOptions({ inheritAttrs: false });

withDefaults(
    defineProps<{
        id?: string;
        unit?: string;
        type?: 'number' | 'date' | 'time';
        placeholder?: string;
        step?: string;
        min?: string;
        max?: string;
        invalid?: boolean;
    }>(),
    { type: 'number', step: 'any' },
);

const model = defineModel<string | number | null>({ required: true });
</script>

<template>
    <div
        :class="[
            'flex h-12 min-w-0 items-center rounded-lg border bg-card px-3.5 focus-within:ring-2 focus-within:ring-ring/30',
            invalid ? 'border-destructive' : 'border-input focus-within:border-primary',
        ]"
    >
        <input
            :id="id"
            v-model="model"
            :type="type"
            :inputmode="type === 'number' ? 'decimal' : undefined"
            :placeholder="placeholder"
            :step="type === 'number' ? step : undefined"
            :min="min"
            :max="max"
            v-bind="$attrs"
            class="h-full min-w-0 flex-1 border-0 bg-transparent p-0 text-base outline-none focus:ring-0"
        />
        <span v-if="unit" class="ml-2 text-sm text-muted-foreground">{{ unit }}</span>
    </div>
</template>
