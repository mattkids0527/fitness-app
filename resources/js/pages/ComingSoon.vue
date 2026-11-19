<script setup lang="ts">
import AppPage from '@/components/AppPage.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Dumbbell, Sparkles, Utensils } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    title: string;
    icon: 'utensils' | 'dumbbell';
    items: string[];
}>();

const icons = { utensils: Utensils, dumbbell: Dumbbell };
const iconComponent = computed(() => icons[props.icon] ?? Sparkles);
</script>

<template>
    <Head :title="title" />
    <AppLayout :breadcrumbs="[{ title, href: '#' }]">
        <AppPage :title="title">
            <div class="grid place-items-center gap-2 px-6 py-16 text-center text-muted-foreground">
                <span class="grid size-16 place-items-center rounded-2xl bg-brand-soft text-primary">
                    <component :is="iconComponent" class="size-7" />
                </span>
                <h2 class="mt-2 text-lg font-semibold text-foreground">即將推出</h2>
                <p>這個功能正在開發中，完成後會包含：</p>
                <ul class="list-disc space-y-1 pl-5 text-left text-sm">
                    <li v-for="item in items" :key="item">{{ item }}</li>
                </ul>
            </div>
        </AppPage>
    </AppLayout>
</template>
