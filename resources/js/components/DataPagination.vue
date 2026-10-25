<script setup>
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { Button } from '@/components/ui/button';

/** Expects a Laravel API-resource paginator: { meta: { links, from, to, total, last_page } }. */
defineProps({
    meta: { type: Object, required: true },
});

const isPrevious = (label) => label.includes('Previous');
const isNext = (label) => label.includes('Next');
</script>

<template>
    <div v-if="meta.total > 0" class="flex flex-col items-center justify-between gap-3 px-2 text-sm text-muted-foreground sm:flex-row">
        <p>{{ meta.from }}–{{ meta.to }} sur {{ meta.total }}</p>
        <nav v-if="meta.last_page > 1" class="flex flex-wrap gap-1" aria-label="Pagination">
            <template v-for="link in meta.links" :key="link.label">
                <Button :variant="link.active ? 'outline' : 'ghost'" size="icon-sm" :disabled="!link.url" :as-child="!!link.url">
                    <component :is="link.url ? Link : 'span'" :href="link.url" preserve-scroll>
                        <ChevronLeft v-if="isPrevious(link.label)" />
                        <ChevronRight v-else-if="isNext(link.label)" />
                        <template v-else>{{ link.label }}</template>
                    </component>
                </Button>
            </template>
        </nav>
    </div>
</template>
