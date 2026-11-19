<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft } from 'lucide-vue-next';

withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        backHref?: string;
        narrow?: boolean;
    }>(),
    { narrow: false },
);
</script>

<template>
    <div :class="['mx-auto w-full px-4 py-4 md:px-8 md:py-8', narrow ? 'max-w-2xl' : 'max-w-5xl']">
        <div v-if="title" class="mb-3 flex min-h-11 items-center gap-2">
            <Link v-if="backHref" :href="backHref" class="-ml-2 grid size-9 place-items-center rounded-full hover:bg-muted" aria-label="返回">
                <ChevronLeft class="size-5" />
            </Link>
            <div class="flex-1">
                <h1 class="text-2xl font-bold tracking-tight">{{ title }}</h1>
                <p v-if="description" class="text-sm text-muted-foreground">{{ description }}</p>
            </div>
            <slot name="actions" />
        </div>
        <slot />
    </div>
</template>
