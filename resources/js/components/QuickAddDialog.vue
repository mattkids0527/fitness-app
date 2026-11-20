<script setup lang="ts">
import ResponsiveDialog from '@/components/ResponsiveDialog.vue';
import { useQuickActions } from '@/composables/useQuickActions';
import { Link } from '@inertiajs/vue3';
import { Camera, Clock, Droplet, Dumbbell, Pencil, Weight } from 'lucide-vue-next';

const { quickAddOpen, openWeightSheet, openWaterSheet } = useQuickActions();

const itemClass = 'flex flex-col items-center gap-2 rounded-xl border bg-card px-2 pb-3 pt-4 text-center transition-colors hover:bg-brand-soft';
const iconClass = 'grid size-[52px] place-items-center rounded-2xl bg-brand-soft text-primary';
const soonClass = 'relative flex flex-col items-center gap-2 rounded-xl border bg-card px-2 pb-3 pt-4 text-center text-muted-foreground';
</script>

<template>
    <ResponsiveDialog v-model:open="quickAddOpen" title="快速新增">
        <!-- 可用的排前面；即將推出的淡化並以時鐘標記 -->
        <div class="grid grid-cols-3 gap-2.5">
            <Link :href="route('fitness.meal.create')" :class="itemClass" @click="quickAddOpen = false">
                <span :class="iconClass"><Pencil class="size-6" /></span>
                <span class="font-semibold">記錄餐點</span>
            </Link>
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
                <span class="grid size-[52px] place-items-center rounded-2xl bg-muted"><Camera class="size-6" /></span>
                <span class="font-semibold">拍照分析<span class="sr-only">（即將推出）</span></span>
            </div>
            <div :class="soonClass" aria-disabled="true">
                <Clock class="absolute right-2 top-2 size-3.5" aria-hidden="true" />
                <span class="grid size-[52px] place-items-center rounded-2xl bg-muted"><Dumbbell class="size-6" /></span>
                <span class="font-semibold">健身<span class="sr-only">（即將推出）</span></span>
            </div>
        </div>
    </ResponsiveDialog>
</template>
