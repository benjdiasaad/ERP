<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { FileMinus, Plus, Search } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import DataPagination from '@/components/DataPagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useFilters } from '@/composables/useFilters';
import { usePermissions } from '@/composables/usePermissions';
import { formatCurrency, formatDate, formatMoney, label, variant } from '@/composables/format';

const props = defineProps({
    notes: { type: Object, required: true },
    filters: { type: Object, required: true },
    statuses: { type: Array, required: true },
    counts: { type: Object, required: true },
});

const { can } = usePermissions();
const filters = useFilters('sales.credit-notes.index', props.filters, { search: '', status: '' });
const statusTab = computed({
    get: () => filters.status || 'all',
    set: (value) => (filters.status = value === 'all' ? '' : value),
});
const total = computed(() => Object.values(props.counts).reduce((sum, c) => sum + c.count, 0));
</script>

<template>
    <AppLayout title="Avoirs" :breadcrumbs="[{ title: 'Ventes' }, { title: 'Avoirs' }]">
        <PageHeader title="Avoirs" description="Remboursements et corrections de factures clients.">
            <template #actions>
                <Button v-if="can('credit_notes.create')" as-child>
                    <Link :href="route('sales.credit-notes.create')"><Plus /> Nouvel avoir</Link>
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-3">
            <Card v-for="status in statuses" :key="status" class="gap-1 py-4">
                <CardHeader class="px-4">
                    <CardDescription>{{ label('creditNoteStatus', status) }}s</CardDescription>
                    <CardTitle class="text-xl tabular-nums">{{ formatCurrency(counts[status].amount) }}</CardTitle>
                    <p class="text-xs text-muted-foreground">{{ counts[status].count }} avoir(s)</p>
                </CardHeader>
            </Card>
        </div>

        <Card class="gap-4 py-4">
            <CardContent class="grid gap-3 px-4">
                <Tabs v-model="statusTab" class="overflow-x-auto">
                    <TabsList>
                        <TabsTrigger value="all">Tous <span class="text-muted-foreground">{{ total }}</span></TabsTrigger>
                        <TabsTrigger v-for="status in statuses" :key="status" :value="status">
                            {{ label('creditNoteStatus', status) }} <span class="text-muted-foreground">{{ counts[status].count }}</span>
                        </TabsTrigger>
                    </TabsList>
                </Tabs>
                <div class="relative">
                    <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="filters.search" type="search" placeholder="Référence ou client…" class="pl-8" aria-label="Rechercher" />
                </div>
            </CardContent>

            <Table v-if="notes.data.length">
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Avoir</TableHead>
                        <TableHead>Client</TableHead>
                        <TableHead class="hidden md:table-cell">Facture</TableHead>
                        <TableHead class="text-right">Montant TTC</TableHead>
                        <TableHead class="pr-4">Statut</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="note in notes.data" :key="note.id">
                        <TableCell class="pl-4">
                            <Link :href="route('sales.credit-notes.show', note.id)" class="font-medium hover:underline">{{ note.reference }}</Link>
                            <p class="text-xs text-muted-foreground">{{ formatDate(note.date) }}</p>
                        </TableCell>
                        <TableCell class="max-w-48 truncate">{{ note.customer?.name ?? '—' }}</TableCell>
                        <TableCell class="hidden text-muted-foreground md:table-cell">{{ note.invoice?.reference ?? '—' }}</TableCell>
                        <TableCell class="text-right tabular-nums">{{ formatMoney(note.total_ttc) }}</TableCell>
                        <TableCell class="pr-4"><Badge :variant="variant('creditNoteStatus', note.status)">{{ label('creditNoteStatus', note.status) }}</Badge></TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <Empty v-else>
                <EmptyHeader>
                    <EmptyMedia variant="icon"><FileMinus /></EmptyMedia>
                    <EmptyTitle>Aucun avoir</EmptyTitle>
                    <EmptyDescription>Aucun avoir ne correspond à ces filtres.</EmptyDescription>
                </EmptyHeader>
            </Empty>

            <DataPagination :meta="notes.meta" class="px-4" />
        </Card>
    </AppLayout>
</template>
