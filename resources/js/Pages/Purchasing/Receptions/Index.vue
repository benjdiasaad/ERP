<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { PackageCheck, Search } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import DataPagination from '@/components/DataPagination.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useFilters } from '@/composables/useFilters';
import { formatDate, label, variant } from '@/composables/format';

const props = defineProps({
    notes: { type: Object, required: true },
    filters: { type: Object, required: true },
    statuses: { type: Array, required: true },
    counts: { type: Object, required: true },
});

const filters = useFilters('purchasing.receptions.index', props.filters, { search: '', status: '' });
const statusTab = computed({
    get: () => filters.status || 'all',
    set: (value) => (filters.status = value === 'all' ? '' : value),
});
const total = computed(() => Object.values(props.counts).reduce((sum, n) => sum + n, 0));
</script>

<template>
    <AppLayout title="Réceptions" :breadcrumbs="[{ title: 'Achats' }, { title: 'Réceptions' }]">
        <PageHeader title="Bons de réception" description="Contrôle des marchandises reçues des fournisseurs. Créez-les depuis un bon de commande confirmé." />

        <Card class="gap-4 py-4">
            <CardContent class="grid gap-3 px-4">
                <Tabs v-model="statusTab" class="overflow-x-auto">
                    <TabsList>
                        <TabsTrigger value="all">Toutes <span class="text-muted-foreground">{{ total }}</span></TabsTrigger>
                        <TabsTrigger v-for="status in statuses" :key="status" :value="status">
                            {{ label('receptionStatus', status) }} <span class="text-muted-foreground">{{ counts[status] }}</span>
                        </TabsTrigger>
                    </TabsList>
                </Tabs>
                <div class="relative">
                    <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="filters.search" type="search" placeholder="Référence, fournisseur ou commande…" class="pl-8" aria-label="Rechercher" />
                </div>
            </CardContent>

            <Table v-if="notes.data.length">
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Réception</TableHead>
                        <TableHead>Fournisseur</TableHead>
                        <TableHead class="hidden md:table-cell">Commande</TableHead>
                        <TableHead class="pr-4">Statut</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="note in notes.data" :key="note.id">
                        <TableCell class="pl-4">
                            <Link :href="route('purchasing.receptions.show', note.id)" class="font-medium hover:underline">{{ note.reference }}</Link>
                            <p class="text-xs text-muted-foreground">{{ formatDate(note.reception_date) }}</p>
                        </TableCell>
                        <TableCell class="max-w-56 truncate">{{ note.supplier?.name ?? '—' }}</TableCell>
                        <TableCell class="hidden text-muted-foreground md:table-cell">{{ note.purchase_order?.reference ?? '—' }}</TableCell>
                        <TableCell class="pr-4"><Badge :variant="variant('receptionStatus', note.status)">{{ label('receptionStatus', note.status) }}</Badge></TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <Empty v-else>
                <EmptyHeader>
                    <EmptyMedia variant="icon"><PackageCheck /></EmptyMedia>
                    <EmptyTitle>Aucune réception</EmptyTitle>
                    <EmptyDescription>Utilisez « Réceptionner » sur un bon de commande confirmé.</EmptyDescription>
                </EmptyHeader>
            </Empty>

            <DataPagination :meta="notes.meta" class="px-4" />
        </Card>
    </AppLayout>
</template>
