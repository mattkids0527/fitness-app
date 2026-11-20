<script setup lang="ts">
import { useToast } from '@/composables/useToast';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { Check } from 'lucide-vue-next';
import { watch } from 'vue';

const { message, show } = useToast();
const page = usePage<SharedData>();

// 後端 redirect 時以 with('success', ...) 帶回的成功訊息；每次回應都是新的 flash 物件，同樣的文字連續出現也會再顯示
watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) {
            show(flash.success);
        }
    },
    { immediate: true },
);
</script>

<template>
    <Transition
        enter-active-class="transition duration-200"
        enter-from-class="-translate-y-2 opacity-0"
        leave-active-class="transition duration-200"
        leave-to-class="-translate-y-2 opacity-0"
    >
        <div
            v-if="message"
            class="fixed left-1/2 top-[calc(env(safe-area-inset-top)+1rem)] z-[60] flex -translate-x-1/2 items-center gap-2 whitespace-nowrap rounded-full bg-foreground px-4 py-2.5 text-sm text-background shadow-lg"
            role="status"
        >
            <Check class="size-4" />
            {{ message }}
        </div>
    </Transition>
</template>
