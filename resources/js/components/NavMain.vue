<script setup lang="ts">
import { SidebarGroup, SidebarMenu, SidebarMenuBadge, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

defineProps<{
    items: NavItem[];
}>();

const page = usePage();

/**
 * 目前網址是否在該項目底下（例如 /me/edit 屬於「我的」）。
 */
const isActive = (href: string) => page.url === href || page.url.startsWith(`${href}/`) || page.url.startsWith(`${href}?`);
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="isActive(item.href)"
                    :tooltip="item.title"
                    class="data-[active=true]:bg-brand-soft data-[active=true]:text-primary"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
                <SidebarMenuBadge v-if="item.badge" class="rounded-full bg-muted px-2 text-[11px] text-muted-foreground">{{
                    item.badge
                }}</SidebarMenuBadge>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
