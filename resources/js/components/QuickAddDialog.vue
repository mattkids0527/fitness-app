<script setup lang="ts">
import ResponsiveDialog from '@/components/ResponsiveDialog.vue';
import { useQuickActions } from '@/composables/useQuickActions';
import { Camera, ChevronRight, Droplet, Dumbbell, Pencil, Weight } from 'lucide-vue-next';

const { quickAddOpen, openWeightSheet } = useQuickActions();

// 尚未實作的項目，之後各模組完成時改成可點
const comingSoon = [
    { title: '拍照分析', description: 'AI 估算營養素', icon: Camera },
    { title: '手動記錄', description: '輸入餐點內容', icon: Pencil },
    { title: '健身', description: '有氧、重訓', icon: Dumbbell },
    { title: '喝水', description: '+250 / +500 ml', icon: Droplet },
];
</script>

<template>
    <ResponsiveDialog v-model:open="quickAddOpen" title="快速新增" description="選擇要記錄的項目">
        <div class="grid grid-cols-2 gap-2.5">
            <div
                v-for="item in comingSoon"
                :key="item.title"
                class="relative flex flex-col gap-2.5 rounded-xl border bg-card p-3.5 text-muted-foreground"
                aria-disabled="true"
            >
                <span class="absolute right-3 top-3 rounded-full bg-muted px-2 py-0.5 text-[11px] font-semibold">即將推出</span>
                <span class="grid size-10 place-items-center rounded-xl bg-muted"><component :is="item.icon" class="size-5" /></span>
                <div>
                    <div class="font-semibold">{{ item.title }}</div>
                    <div class="text-xs">{{ item.description }}</div>
                </div>
            </div>
            <button
                type="button"
                class="col-span-2 flex items-center gap-3 rounded-xl border bg-card p-3.5 text-left transition-colors hover:bg-brand-soft"
                @click="openWeightSheet"
            >
                <span class="grid size-10 place-items-center rounded-xl bg-brand-soft text-primary"><Weight class="size-5" /></span>
                <div class="flex-1">
                    <div class="font-semibold">記錄體重</div>
                    <div class="text-xs text-muted-foreground">建議每天固定時間量</div>
                </div>
                <ChevronRight class="size-4 text-muted-foreground" />
            </button>
        </div>
    </ResponsiveDialog>
</template>
