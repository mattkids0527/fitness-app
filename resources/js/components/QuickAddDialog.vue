<script setup lang="ts">
import ResponsiveDialog from '@/components/ResponsiveDialog.vue';
import { useMealPhoto } from '@/composables/useMealPhoto';
import { useQuickActions } from '@/composables/useQuickActions';
import type { AnalyzeMode } from '@/types/meal';
import { Link, router } from '@inertiajs/vue3';
import { Camera, Clock, Droplet, Dumbbell, ImageUp, Pencil, Weight } from 'lucide-vue-next';

const { quickAddOpen, openWeightSheet, openWaterSheet } = useQuickActions();
const { setPhoto } = useMealPhoto();

const itemClass = 'flex flex-col items-center gap-2 rounded-xl border bg-card px-2 pb-3 pt-4 text-center transition-colors hover:bg-brand-soft';
const newClass = `${itemClass} relative cursor-pointer border-primary/50 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring/30`;
const newBadgeClass = 'absolute right-1.5 top-1.5 rounded-full bg-primary px-1.5 text-[10px] text-primary-foreground';
const iconClass = 'grid size-[52px] place-items-center rounded-2xl bg-brand-soft text-primary';
const soonClass = 'relative flex flex-col items-center gap-2 rounded-xl border bg-card px-2 pb-3 pt-4 text-center text-muted-foreground';

/**
 * 拍好或選好照片後直接進分析頁；照片留在瀏覽器記憶體，不先上傳。取消選擇時不動作。
 */
function onPicked(event: Event, mode: AnalyzeMode) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';

    if (!file) {
        return;
    }

    setPhoto(file, mode);
    quickAddOpen.value = false;
    router.visit(route('fitness.meal.analyze', { mode }));
}
</script>

<template>
    <ResponsiveDialog v-model:open="quickAddOpen" title="快速新增">
        <!-- 可用的排前面；即將推出的淡化並以時鐘標記。拍照只在手機顯示（桌機沒有後鏡頭） -->
        <div class="grid grid-cols-3 gap-2.5">
            <Link :href="route('fitness.meal.create')" :class="itemClass" @click="quickAddOpen = false">
                <span :class="iconClass"><Pencil class="size-6" /></span>
                <span class="font-semibold">記錄餐點</span>
            </Link>
            <label :class="[newClass, 'md:hidden']">
                <span :class="newBadgeClass" aria-hidden="true">新</span>
                <span :class="iconClass"><Camera class="size-6" /></span>
                <span class="font-semibold">拍照</span>
                <input type="file" accept="image/*" capture="environment" class="sr-only" @change="onPicked($event, 'camera')" />
            </label>
            <label :class="newClass">
                <span :class="newBadgeClass" aria-hidden="true">新</span>
                <span :class="iconClass"><ImageUp class="size-6" /></span>
                <span class="font-semibold">上傳照片</span>
                <input type="file" accept="image/*" class="sr-only" @change="onPicked($event, 'upload')" />
            </label>
            <button type="button" :class="itemClass" @click="openWaterSheet">
                <span :class="iconClass"><Droplet class="size-6" /></span>
                <span class="font-semibold">喝水</span>
            </button>
            <button type="button" :class="itemClass" @click="openWeightSheet">
                <span :class="iconClass"><Weight class="size-6" /></span>
                <span class="font-semibold">體重</span>
            </button>
            <div :class="soonClass" aria-disabled="true">
                <Clock class="absolute right-2 top-2 size-3.5" aria-hidden="true" />
                <span class="grid size-[52px] place-items-center rounded-2xl bg-muted"><Dumbbell class="size-6" /></span>
                <span class="font-semibold">健身<span class="sr-only">（即將推出）</span></span>
            </div>
        </div>
    </ResponsiveDialog>
</template>
