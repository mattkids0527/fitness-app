<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { useQuickActions } from '@/composables/useQuickActions';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Dumbbell, House, Plus, User, Utensils } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const { openQuickAdd } = useQuickActions();

const mainNavItems: NavItem[] = [
    { title: '今日', href: route('fitness.daily_summary.show', undefined, false), icon: House },
    { title: '飲食', href: route('coming_soon.meals', undefined, false), icon: Utensils, badge: '即將推出' },
    { title: '健身', href: route('coming_soon.workouts', undefined, false), icon: Dumbbell, badge: '即將推出' },
    { title: '我的', href: route('fitness.profile.show', undefined, false), icon: User },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('fitness.daily_summary.show')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
                <SidebarMenuItem class="mt-2">
                    <SidebarMenuButton
                        tooltip="快速新增"
                        class="justify-center bg-primary font-semibold text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground active:bg-primary/90 active:text-primary-foreground"
                        @click="openQuickAdd"
                    >
                        <Plus />
                        <span>快速新增</span>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
