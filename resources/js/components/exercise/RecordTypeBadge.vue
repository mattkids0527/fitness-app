<script setup lang="ts">
import { RECORD_TYPE_ICONS } from '@/composables/useRecordType';
import type { RecordType } from '@/types/exercise';

defineProps<{
    type: RecordType;
    label: string;
}>();
</script>

<template>
    <!-- 記錄方式標籤：圖示＋名稱 -->
    <span
        class="inline-flex h-5 shrink-0 items-center gap-[3px] whitespace-nowrap rounded-full bg-muted px-[7px] text-[11px] font-semibold text-muted-foreground"
    >
        <component :is="RECORD_TYPE_ICONS[type]" class="size-3" aria-hidden="true" />{{ label }}
    </span>
</template>
