<script setup lang="ts">
import { useQuickActions } from '@/composables/useQuickActions';
import { Link, usePage } from '@inertiajs/vue3';
import { Dumbbell, House, Plus, User, Utensils } from 'lucide-vue-next';
import type { Component } from 'vue';

interface Tab {
    title: string;
    href: string;
    icon: Component;
}

const page = usePage();
const { openQuickAdd } = useQuickActions();

const leftTabs: Tab[] = [
    { title: '今日', href: route('fitness.daily_summary.show', undefined, false), icon: House },
    { title: '飲食', href: route('coming_soon.meals', undefined, false), icon: Utensils },
];

const rightTabs: Tab[] = [
    { title: '健身', href: route('coming_soon.workouts', undefined, false), icon: Dumbbell },
    { title: '我的', href: route('fitness.profile.show', undefined, false), icon: User },
];

/**
 * 目前網址是否在該 Tab 底下（例如 /me/edit 屬於「我的」）。
 */
const isActive = (href: string) => page.url === href || page.url.startsWith(`${href}/`) || page.url.startsWith(`${href}?`);
</script>

<template>
    <nav
        class="fixed inset-x-0 bottom-0 z-40 border-t bg-background/90 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden"
        aria-label="主要導覽"
    >
        <div class="grid grid-cols-5 items-end px-2 py-1.5">
            <Link
                v-for="tab in leftTabs"
                :key="tab.href"
                :href="tab.href"
                :class="[
                    'flex flex-col items-center gap-0.5 py-1 text-[11px]',
                    isActive(tab.href) ? 'font-semibold text-primary' : 'text-muted-foreground',
                ]"
            >
                <component :is="tab.icon" class="size-5" />
                <span>{{ tab.title }}</span>
            </Link>
            <button type="button" class="flex justify-center" aria-label="快速新增" @click="openQuickAdd">
                <span
                    class="-mt-6 grid size-[52px] place-items-center rounded-full border-4 border-page bg-primary text-primary-foreground shadow-lg shadow-primary/30"
                >
                    <Plus class="size-6" />
                </span>
            </button>
            <Link
                v-for="tab in rightTabs"
                :key="tab.href"
                :href="tab.href"
                :class="[
                    'flex flex-col items-center gap-0.5 py-1 text-[11px]',
                    isActive(tab.href) ? 'font-semibold text-primary' : 'text-muted-foreground',
                ]"
            >
                <component :is="tab.icon" class="size-5" />
                <span>{{ tab.title }}</span>
            </Link>
        </div>
    </nav>
</template>
