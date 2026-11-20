<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    for: string;
    required?: boolean;
    icon?: Component;
    iconClass?: string;
}>();
</script>

<template>
    <!-- 表單欄位標籤：可加圖示；必填欄位打橘色 *，選填不另外標示 -->
    <label :for="$props.for" class="mb-1 flex items-center gap-1 text-xs text-muted-foreground">
        <component :is="icon" v-if="icon" :class="['size-3.5 shrink-0', iconClass]" aria-hidden="true" />
        <slot />
        <span v-if="required" class="text-primary" aria-hidden="true">*</span>
    </label>
</template>
