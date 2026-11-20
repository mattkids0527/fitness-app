<script setup lang="ts">
import { addDays, longDate } from '@/composables/useDateFormat';
import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-vue-next';

const props = defineProps<{
    date: string;
    today: string;
}>();

const emit = defineEmits<{ change: [date: string] }>();

/**
 * 切到指定日期；未來日期不切換。
 */
function go(date: string) {
    if (date && date <= props.today && date !== props.date) {
        emit('change', date);
    }
}

/**
 * 點日期文字時直接打開日期選擇器（不支援 showPicker 的瀏覽器維持原生行為）。
 */
function openPicker(event: MouseEvent) {
    try {
        (event.target as HTMLInputElement).showPicker();
    } catch {
        // 原生行為即可
    }
}
</script>

<template>
    <div class="flex items-center gap-1 md:max-w-xs">
        <button type="button" class="grid size-10 place-items-center rounded-lg hover:bg-muted" aria-label="前一天" @click="go(addDays(date, -1))">
            <ChevronLeft class="size-5" />
        </button>
        <label class="relative flex h-10 flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-lg font-semibold hover:bg-muted">
            <CalendarDays class="size-4" />
            {{ longDate(date) }}{{ date === today ? '（今天）' : '' }}
            <input
                type="date"
                class="absolute inset-0 cursor-pointer opacity-0"
                :value="date"
                :max="today"
                aria-label="選擇日期"
                @click="openPicker"
                @change="go(($event.target as HTMLInputElement).value)"
            />
        </label>
        <button
            type="button"
            class="grid size-10 place-items-center rounded-lg hover:bg-muted disabled:cursor-not-allowed disabled:opacity-30 disabled:hover:bg-transparent"
            aria-label="後一天"
            :disabled="date >= today"
            @click="go(addDays(date, 1))"
        >
            <ChevronRight class="size-5" />
        </button>
    </div>
</template>
