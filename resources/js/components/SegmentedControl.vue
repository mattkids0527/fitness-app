<script setup lang="ts" generic="T extends string">
defineProps<{
    options: { value: T; label: string }[];
}>();

const model = defineModel<T | null>({ required: true });
</script>

<template>
    <div class="grid auto-cols-fr grid-flow-col gap-1 rounded-xl bg-foreground/[0.07] p-1" role="radiogroup">
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            role="radio"
            :aria-checked="model === option.value"
            :class="[
                'h-10 rounded-lg text-sm transition-colors',
                model === option.value
                    ? 'bg-card font-semibold text-foreground shadow-sm'
                    : 'font-medium text-muted-foreground hover:text-foreground',
            ]"
            @click="model = option.value"
        >
            {{ option.label }}
        </button>
    </div>
</template>
