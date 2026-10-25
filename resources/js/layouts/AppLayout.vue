<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppSidebar from '@/components/AppSidebar.vue';
import {
    Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { Separator } from '@/components/ui/separator';
import { SidebarInset, SidebarProvider, SidebarTrigger } from '@/components/ui/sidebar';

/**
 * breadcrumbs: [{ title, href? }] — the last item is the current page.
 */
const props = defineProps({
    title: { type: String, default: null },
    breadcrumbs: { type: Array, default: () => [] },
});
</script>

<template>
    <Head :title="props.title" />

    <SidebarProvider>
        <AppSidebar />
        <SidebarInset class="min-w-0">
            <header class="flex h-14 shrink-0 items-center gap-2 border-b px-4">
                <SidebarTrigger class="-ml-1" />
                <Separator orientation="vertical" class="mr-2 data-[orientation=vertical]:h-4" />
                <Breadcrumb>
                    <BreadcrumbList>
                        <template v-for="(crumb, index) in breadcrumbs" :key="crumb.title">
                            <BreadcrumbItem :class="index < breadcrumbs.length - 1 ? 'hidden md:block' : ''">
                                <BreadcrumbLink v-if="crumb.href && index < breadcrumbs.length - 1" as-child>
                                    <Link :href="crumb.href">{{ crumb.title }}</Link>
                                </BreadcrumbLink>
                                <BreadcrumbPage v-else>{{ crumb.title }}</BreadcrumbPage>
                            </BreadcrumbItem>
                            <BreadcrumbSeparator v-if="index < breadcrumbs.length - 1" class="hidden md:block" />
                        </template>
                    </BreadcrumbList>
                </Breadcrumb>
            </header>

            <main class="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <slot />
            </main>
        </SidebarInset>
    </SidebarProvider>
</template>
