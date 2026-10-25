<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { FileText, Plus, Search } from '@lucide/vue';
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
    quotes: { type: Object, required: true },
    filters: { type: Object, required: true },
    statuses: { type: Array, required: true },
    counts: { type: Object, required: true },
});

const { can } = usePermissions();
const filters = useFilters('sales.quotes.index', props.filters, { search: '', status: '', customer_id: '' });
const statusTab = computed({
    get: () => filters.status || 'all',
    set: (value) => (filters.status = value === 'all' ? '' : value),
});
const total = computed(() => Object.values(props.counts).reduce((sum, c) => sum + c.count, 0));

const summary = computed(() => [
    { label: 'Brouillons', value: formatCurrency(props.counts.draft.amount), hint: `${props.counts.draft.count} devis` },
    { label: 'Envoyés, en attente', value: formatCurrency(props.counts.sent.amount), hint: `${props.counts.sent.count} devis` },
    { label: 'Acceptés', value: formatCurrency(props.counts.accepted.amount + props.counts.converted.amount), hint: `${props.counts.accepted.count + props.counts.converted.count} devis` },
    { label: 'Refusés', value: formatCurrency(props.counts.rejected.amount), hint: `${props.counts.rejected.count} devis` },
]);

const isOverdue = (quote) => quote.status === 'sent' && quote.validity_date && new Date(quote.validity_date) < new Date(new Date().toDateString());
</script>

<template>
    <AppLayout title="Devis" :breadcrumbs="[{ title: 'Ventes' }, { title: 'Devis' }]">
        <PageHeader title="Devis" description="Propositions commerciales, du brouillon à la commande.">
            <template #actions>
                <Button v-if="can('quotes.create')" as-child>
                    <Link :href="route('sales.quotes.create')"><Plus /> Nouveau devis</Link>
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card v-for="item in summary" :key="item.label" class="gap-1 py-4">
                <CardHeader class="px-4">
                    <CardDescription>{{ item.label }}</CardDescription>
                    <CardTitle class="text-xl tabular-nums">{{ item.value }}</CardTitle>
                    <p class="text-xs text-muted-foreground">{{ item.hint }}</p>
                </CardHeader>
            </Card>
        </div>

        <Card class="gap-4 py-4">
            <CardContent class="grid gap-3 px-4">
                <Tabs v-model="statusTab" class="overflow-x-auto">
                    <TabsList>
                        <TabsTrigger value="all">Tous <span class="text-muted-foreground">{{ total }}</span></TabsTrigger>
                        <TabsTrigger v-for="status in statuses" :key="status" :value="status">
                            {{ label('quoteStatus', status) }} <span class="text-muted-foreground">{{ counts[status].count }}</span>
                        </TabsTrigger>
                    </TabsList>
                </Tabs>
                <div class="relative">
                    <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="filters.search" type="search" placeholder="Référence ou client…" class="pl-8" aria-label="Rechercher" />
                </div>
                <p v-if="filters.customer_id" class="text-sm text-muted-foreground">
                    Filtré sur un client ·
                    <button type="button" class="underline hover:text-foreground" @click="filters.customer_id = ''">voir tous les devis</button>
                </p>
            </CardContent>

            <Table v-if="quotes.data.length">
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Devis</TableHead>
                        <TableHead>Client</TableHead>
                        <TableHead class="hidden md:table-cell">Validité</TableHead>
                        <TableHead class="text-right">Total TTC</TableHead>
                        <TableHead class="pr-4">Statut</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="quote in quotes.data" :key="quote.id">
                        <TableCell class="pl-4">
                            <Link :href="route('sales.quotes.show', quote.id)" class="font-medium hover:underline">{{ quote.reference }}</Link>
                            <p class="text-xs text-muted-foreground">{{ formatDate(quote.quote_date) }}</p>
                        </TableCell>
                        <TableCell class="max-w-56 truncate">{{ quote.customer?.name ?? '—' }}</TableCell>
                        <TableCell class="hidden md:table-cell" :class="isOverdue(quote) ? 'font-medium text-destructive' : 'text-muted-foreground'">
                            {{ formatDate(quote.validity_date) }}
                        </TableCell>
                        <TableCell class="text-right tabular-nums">{{ formatMoney(quote.total_ttc) }}</TableCell>
                        <TableCell class="pr-4"><Badge :variant="variant('quoteStatus', quote.status)">{{ label('quoteStatus', quote.status) }}</Badge></TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <Empty v-else>
                <EmptyHeader>
                    <EmptyMedia variant="icon"><FileText /></EmptyMedia>
                    <EmptyTitle>Aucun devis</EmptyTitle>
                    <EmptyDescription>Aucun devis ne correspond à ces filtres.</EmptyDescription>
                </EmptyHeader>
            </Empty>

            <DataPagination :meta="quotes.meta" class="px-4" />
        </Card>
    </AppLayout>
</template>
