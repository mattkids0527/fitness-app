<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';

const sidebarNavItems: NavItem[] = [
    {
        title: '個人帳號',
        href: '/settings/profile',
    },
    {
        title: '密碼',
        href: '/settings/password',
    },
    {
        title: '外觀',
        href: '/settings/appearance',
    },
    {
        title: '已連結帳號',
        href: '/settings/connected-accounts',
    },
    {
        title: '版本',
        href: '/settings/version',
    },
];

const currentPath = window.location.pathname;
</script>

<template>
    <div class="px-4 py-6">
        <Heading title="設定" description="管理帳號、密碼、外觀與已連結帳號，查看目前版本" />

        <div class="flex flex-col space-y-8 md:space-y-0 lg:flex-row lg:space-x-12 lg:space-y-0">
            <aside class="w-full max-w-xl lg:w-48">
                <nav class="flex flex-col space-x-0 space-y-1">
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="item.href"
                        variant="ghost"
                        :class="[
                            'w-full justify-start',
                            { 'bg-brand-soft font-semibold text-primary hover:bg-brand-soft hover:text-primary': currentPath === item.href },
                        ]"
                        as-child
                    >
                        <Link :href="item.href">
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 md:hidden" />

            <div class="flex-1 md:max-w-2xl">
                <section class="max-w-xl space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
