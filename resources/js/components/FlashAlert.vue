<script setup lang="ts">
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { AlertCircle } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage<SharedData>();

// 後端 redirect 時以 with('error', {title, message}) 帶回的一次性錯誤，換頁後自然消失
const error = computed(() => page.props.flash?.error);
</script>

<template>
    <div
        v-if="error"
        role="alert"
        class="flex gap-2.5 rounded-[10px] border border-destructive/30 bg-destructive/5 p-3 text-[13px] leading-relaxed text-destructive dark:bg-destructive/15 dark:text-red-400"
    >
        <AlertCircle class="mt-0.5 size-4 shrink-0" />
        <div>
            <p class="text-sm font-semibold">{{ error.title }}</p>
            <p class="text-foreground/80">{{ error.message }}</p>
        </div>
    </div>
</template>
