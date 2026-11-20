<script setup lang="ts">
import ResponsiveDialog from '@/components/ResponsiveDialog.vue';
import { useQuickActions } from '@/composables/useQuickActions';
import { Link } from '@inertiajs/vue3';
import { Camera, ChevronRight, Droplet, Dumbbell, Pencil, Weight } from 'lucide-vue-next';

const { quickAddOpen, openWeightSheet, openWaterSheet } = useQuickActions();
</script>

<template>
    <ResponsiveDialog v-model:open="quickAddOpen" title="快速新增" description="選擇要記錄的項目">
        <div class="grid grid-cols-2 gap-2.5">
            <div class="relative flex flex-col gap-2.5 rounded-xl border bg-card p-3.5 text-muted-foreground" aria-disabled="true">
                <span class="absolute right-3 top-3 rounded-full bg-muted px-2 py-0.5 text-[11px] font-semibold">即將推出</span>
                <span class="grid size-10 place-items-center rounded-xl bg-muted"><Camera class="size-5" /></span>
                <div>
                    <div class="font-semibold">拍照分析</div>
                    <div class="text-xs">AI 估算營養素</div>
                </div>
            </div>
            <Link
                :href="route('fitness.meal.create')"
                class="flex flex-col gap-2.5 rounded-xl border bg-card p-3.5 text-left transition-colors hover:bg-brand-soft"
                @click="quickAddOpen = false"
            >
                <span class="grid size-10 place-items-center rounded-xl bg-brand-soft text-primary"><Pencil class="size-5" /></span>
                <div>
                    <div class="font-semibold">手動記錄</div>
                    <div class="text-xs text-muted-foreground">輸入餐點內容</div>
                </div>
            </Link>
            <div class="relative flex flex-col gap-2.5 rounded-xl border bg-card p-3.5 text-muted-foreground" aria-disabled="true">
                <span class="absolute right-3 top-3 rounded-full bg-muted px-2 py-0.5 text-[11px] font-semibold">即將推出</span>
                <span class="grid size-10 place-items-center rounded-xl bg-muted"><Dumbbell class="size-5" /></span>
                <div>
                    <div class="font-semibold">健身</div>
                    <div class="text-xs">有氧、重訓</div>
                </div>
            </div>
            <button
                type="button"
                class="flex flex-col gap-2.5 rounded-xl border bg-card p-3.5 text-left transition-colors hover:bg-brand-soft"
                @click="openWaterSheet"
            >
                <span class="grid size-10 place-items-center rounded-xl bg-brand-soft text-primary"><Droplet class="size-5" /></span>
                <div>
                    <div class="font-semibold">喝水</div>
                    <div class="text-xs text-muted-foreground">+250 / +500 ml</div>
                </div>
            </button>
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
