<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Building2, Check, ChevronsUpDown } from '@lucide/vue';
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem, useSidebar } from '@/components/ui/sidebar';

const page = usePage();
const { isMobile } = useSidebar();

const companies = computed(() => page.props.auth.companies);
const current = computed(() => companies.value.find((c) => c.id === page.props.auth.currentCompanyId));

const switchTo = (company) => {
    if (company.id !== current.value?.id) {
        router.post(route('company.switch'), { company_id: company.id });
    }
};
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton size="lg" class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground">
                        <div class="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                            <Building2 class="size-4" />
                        </div>
                        <div class="grid flex-1 text-left text-sm leading-tight">
                            <span class="truncate font-semibold">{{ current?.name ?? 'Aucune société' }}</span>
                            <span class="truncate text-xs text-muted-foreground">{{ page.props.app.name }}</span>
                        </div>
                        <ChevronsUpDown v-if="companies.length > 1" class="ml-auto" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent v-if="companies.length > 1" class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                    align="start" :side="isMobile ? 'bottom' : 'right'" :side-offset="4">
                    <DropdownMenuLabel class="text-xs text-muted-foreground">Sociétés</DropdownMenuLabel>
                    <DropdownMenuItem v-for="company in companies" :key="company.id" class="gap-2 p-2" @select="switchTo(company)">
                        <div class="flex size-6 items-center justify-center rounded-sm border">
                            <Building2 class="size-3.5 shrink-0" />
                        </div>
                        {{ company.name }}
                        <Check v-if="company.id === current?.id" class="ml-auto size-4" />
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
