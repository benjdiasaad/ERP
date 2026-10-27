<script setup>
import { Link } from '@inertiajs/vue3';
import { CalendarDays, Plus, Search } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import DataPagination from '@/components/DataPagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useFilters } from '@/composables/useFilters';
import { usePermissions } from '@/composables/usePermissions';
import { formatDateTime, label, variant } from '@/composables/format';

const props = defineProps({
    events: { type: Object, required: true },
    counts: { type: Object, required: true },
    categories: { type: Array, required: true },
    filters: { type: Object, required: true },
});

const { can } = usePermissions();
const filters = useFilters('organisation.events.index', props.filters, { search: '', status: '', event_category_id: '', period: '' });
</script>

<template>
    <AppLayout title="Événements" :breadcrumbs="[{ title: 'Organisation' }, { title: 'Événements' }]">
        <PageHeader title="Événements" description="Réunions, formations, séminaires et autres événements de la société.">
            <template #actions>
                <Button v-if="can('events.create')" as-child>
                    <Link :href="route('organisation.events.create')"><Plus /> Nouvel événement</Link>
                </Button>
            </template>
        </PageHeader>

        <Tabs v-model="filters.status" class="overflow-x-auto">
            <TabsList>
                <TabsTrigger value="">Tous</TabsTrigger>
                <TabsTrigger v-for="(count, status) in counts" :key="status" :value="status">
                    {{ label('eventStatus', status) }}<span v-if="count" class="ml-1 text-muted-foreground tabular-nums">{{ count }}</span>
                </TabsTrigger>
            </TabsList>
        </Tabs>

        <Card class="gap-4 py-4">
            <CardContent class="flex flex-col gap-3 px-4 sm:flex-row">
                <div class="relative flex-1">
                    <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="filters.search" type="search" placeholder="Titre, référence, lieu…" class="pl-8" aria-label="Rechercher" />
                </div>
                <NativeSelect v-model="filters.event_category_id" aria-label="Catégorie" class="sm:w-44">
                    <option value="">Toutes catégories</option>
                    <option v-for="c in categories" :key="c.value" :value="c.value">{{ c.label }}</option>
                </NativeSelect>
                <NativeSelect v-model="filters.period" aria-label="Période" class="sm:w-36">
                    <option value="">Toutes dates</option>
                    <option value="upcoming">À venir</option>
                    <option value="past">Passés</option>
                </NativeSelect>
            </CardContent>

            <Table v-if="events.data.length">
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Événement</TableHead>
                        <TableHead>Date</TableHead>
                        <TableHead class="hidden text-right md:table-cell">Participants</TableHead>
                        <TableHead class="pr-4">Statut</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="event in events.data" :key="event.id">
                        <TableCell class="pl-4">
                            <div class="flex items-center gap-2">
                                <span class="size-2.5 shrink-0 rounded-full bg-muted-foreground" :style="event.category?.color ? { backgroundColor: event.category.color } : null" aria-hidden="true" />
                                <Link :href="route('organisation.events.show', event.id)" class="font-medium hover:underline">{{ event.title }}</Link>
                            </div>
                            <p class="text-xs text-muted-foreground">{{ [label('eventType', event.type), event.location].filter(Boolean).join(' · ') }}</p>
                        </TableCell>
                        <TableCell class="whitespace-nowrap text-muted-foreground">{{ formatDateTime(event.start_date) }}</TableCell>
                        <TableCell class="hidden text-right tabular-nums md:table-cell">{{ event.participants_count ?? 0 }}</TableCell>
                        <TableCell class="pr-4"><Badge :variant="variant('eventStatus', event.status)">{{ label('eventStatus', event.status) }}</Badge></TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <Empty v-else>
                <EmptyHeader>
                    <EmptyMedia variant="icon"><CalendarDays /></EmptyMedia>
                    <EmptyTitle>Aucun événement</EmptyTitle>
                    <EmptyDescription>Aucun événement ne correspond à ces filtres.</EmptyDescription>
                </EmptyHeader>
            </Empty>

            <DataPagination :meta="events.meta" class="px-4" />
        </Card>
    </AppLayout>
</template>
