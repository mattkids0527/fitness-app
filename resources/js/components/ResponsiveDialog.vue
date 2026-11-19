<script setup lang="ts">
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { useMediaQuery } from '@vueuse/core';

defineProps<{
    title: string;
    description?: string;
}>();

const open = defineModel<boolean>('open', { required: true });

// 與 Tailwind md 斷點一致：手機為底部面板、桌機為置中對話框
const isDesktop = useMediaQuery('(min-width: 768px)');
</script>

<template>
    <Dialog v-if="isDesktop" v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription v-if="description">{{ description }}</DialogDescription>
            </DialogHeader>
            <slot />
        </DialogContent>
    </Dialog>
    <Sheet v-else v-model:open="open">
        <SheetContent side="bottom" class="max-h-[90dvh] overflow-y-auto rounded-t-2xl px-4 pb-[calc(env(safe-area-inset-bottom)+1.5rem)] pt-3">
            <div class="mx-auto mb-3 h-1.5 w-10 rounded-full bg-muted-foreground/25" />
            <SheetHeader class="text-left">
                <SheetTitle>{{ title }}</SheetTitle>
                <SheetDescription v-if="description">{{ description }}</SheetDescription>
            </SheetHeader>
            <div class="mt-4">
                <slot />
            </div>
        </SheetContent>
    </Sheet>
</template>
