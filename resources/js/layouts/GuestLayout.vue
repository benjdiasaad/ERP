<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { Building2 } from '@lucide/vue';

defineProps({
    title: { type: String, default: null },
});

const page = usePage();
</script>

<template>
    <Head :title="title" />

    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-muted p-6 md:p-10">
        <div class="flex w-full max-w-sm flex-col gap-6">
            <div class="flex items-center gap-2 self-center font-semibold">
                <div class="flex size-7 items-center justify-center rounded-md bg-primary text-primary-foreground">
                    <Building2 class="size-4" />
                </div>
                {{ page.props.app.name }}
            </div>
            <slot />
        </div>
    </div>
</template>
