<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    Sidebar, SidebarContent, SidebarFooter, SidebarGroup, SidebarGroupLabel, SidebarHeader,
    SidebarMenu, SidebarMenuBadge, SidebarMenuButton, SidebarMenuItem, SidebarRail,
} from '@/components/ui/sidebar';
import CompanySwitcher from '@/components/CompanySwitcher.vue';
import NavUser from '@/components/NavUser.vue';
import { navigation } from '@/navigation';
import { usePermissions } from '@/composables/usePermissions';

const { can } = usePermissions();

// Hide items the user may not see; drop groups left empty.
const sections = computed(() => navigation
    .map((section) => ({ ...section, items: section.items.filter((item) => !item.permission || can(item.permission)) }))
    .filter((section) => section.items.length > 0));

const isActive = (item) => Boolean(item.route) && route().current(item.active ?? item.route);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <CompanySwitcher />
        </SidebarHeader>

        <SidebarContent>
            <SidebarGroup v-for="section in sections" :key="section.label ?? 'main'">
                <SidebarGroupLabel v-if="section.label">{{ section.label }}</SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem v-for="item in section.items" :key="item.label">
                        <SidebarMenuButton v-if="item.route" as-child :is-active="isActive(item)" :tooltip="item.label">
                            <Link :href="route(item.route)">
                                <component :is="item.icon" />
                                <span>{{ item.label }}</span>
                            </Link>
                        </SidebarMenuButton>
                        <template v-else>
                            <SidebarMenuButton disabled :tooltip="`${item.label} (bientôt)`" class="opacity-60">
                                <component :is="item.icon" />
                                <span>{{ item.label }}</span>
                            </SidebarMenuButton>
                            <SidebarMenuBadge class="text-[10px] text-muted-foreground">Bientôt</SidebarMenuBadge>
                        </template>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
        <SidebarRail />
    </Sidebar>
</template>
