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
    invoices: { type: Object, required: true },
    filters: { type: Object, required: true },
    statuses: { type: Array, required: true },
    counts: { type: Object, required: true },
});

const { can } = usePermissions();
const filters = useFilters('sales.invoices.index', props.filters, { search: '', status: '', customer_id: '' });
const statusTab = computed({
    get: () => filters.status || 'all',
    set: (value) => (filters.status = value === 'all' ? '' : value),
});
const c = props.counts;
const total = computed(() => Object.values(c).reduce((sum, x) => sum + x.count, 0));
const unpaidCount = computed(() => c.sent.count + c.partial.count + c.overdue.count);

const summary = computed(() => [
    { label: 'À encaisser', value: formatCurrency(c.sent.due + c.partial.due + c.overdue.due), hint: `${unpaidCount.value} facture(s)` },
    { label: 'En retard', value: formatCurrency(c.overdue.due), hint: `${c.overdue.count} facture(s)`, danger: c.overdue.count > 0 },
    { label: 'Encaissé (payées)', value: formatCurrency(c.paid.amount), hint: `${c.paid.count} facture(s)` },
    { label: 'Brouillons', value: formatCurrency(c.draft.amount), hint: `${c.draft.count} facture(s)` },
]);
</script>

<template>
    <AppLayout title="Factures" :breadcrumbs="[{ title: 'Ventes' }, { title: 'Factures' }]">
        <PageHeader title="Factures clients" description="Facturation, encaissements et relances.">
            <template #actions>
                <Button v-if="can('invoices.create')" as-child>
                    <Link :href="route('sales.invoices.create')"><Plus /> Nouvelle facture</Link>
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card v-for="item in summary" :key="item.label" class="gap-1 py-4">
                <CardHeader class="px-4">
                    <CardDescription>{{ item.label }}</CardDescription>
                    <CardTitle class="text-xl tabular-nums" :class="item.danger ? 'text-destructive' : ''">{{ item.value }}</CardTitle>
                    <p class="text-xs text-muted-foreground">{{ item.hint }}</p>
                </CardHeader>
            </Card>
        </div>

        <Card class="gap-4 py-4">
            <CardContent class="grid gap-3 px-4">
                <Tabs v-model="statusTab" class="overflow-x-auto">
                    <TabsList>
                        <TabsTrigger value="all">Toutes <span class="text-muted-foreground">{{ total }}</span></TabsTrigger>
                        <TabsTrigger value="unpaid">Non payées <span class="text-muted-foreground">{{ unpaidCount }}</span></TabsTrigger>
                        <TabsTrigger v-for="status in statuses" :key="status" :value="status">
                            {{ label('invoiceStatus', status) }} <span class="text-muted-foreground">{{ counts[status].count }}</span>
                        </TabsTrigger>
                    </TabsList>
                </Tabs>
                <div class="relative">
                    <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="filters.search" type="search" placeholder="Référence ou client…" class="pl-8" aria-label="Rechercher" />
                </div>
            </CardContent>

            <Table v-if="invoices.data.length">
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Facture</TableHead>
                        <TableHead>Client</TableHead>
                        <TableHead class="hidden md:table-cell">Échéance</TableHead>
                        <TableHead class="hidden text-right sm:table-cell">Total TTC</TableHead>
                        <TableHead class="text-right">Reste dû</TableHead>
                        <TableHead class="pr-4">Statut</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="invoice in invoices.data" :key="invoice.id">
                        <TableCell class="pl-4">
                            <Link :href="route('sales.invoices.show', invoice.id)" class="font-medium hover:underline">{{ invoice.reference }}</Link>
                            <p class="text-xs text-muted-foreground">{{ formatDate(invoice.invoice_date) }}</p>
                        </TableCell>
                        <TableCell class="max-w-48 truncate">{{ invoice.customer?.name ?? '—' }}</TableCell>
                        <TableCell class="hidden md:table-cell" :class="invoice.status === 'overdue' ? 'font-medium text-destructive' : 'text-muted-foreground'">
                            {{ formatDate(invoice.due_date) }}
                        </TableCell>
                        <TableCell class="hidden text-right tabular-nums sm:table-cell">{{ formatMoney(invoice.total_ttc) }}</TableCell>
                        <TableCell class="text-right tabular-nums">{{ Number(invoice.amount_due) ? formatMoney(invoice.amount_due) : '—' }}</TableCell>
                        <TableCell class="pr-4"><Badge :variant="variant('invoiceStatus', invoice.status)">{{ label('invoiceStatus', invoice.status) }}</Badge></TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <Empty v-else>
                <EmptyHeader>
                    <EmptyMedia variant="icon"><FileText /></EmptyMedia>
                    <EmptyTitle>Aucune facture</EmptyTitle>
                    <EmptyDescription>Aucune facture ne correspond à ces filtres.</EmptyDescription>
                </EmptyHeader>
            </Empty>

            <DataPagination :meta="invoices.meta" class="px-4" />
        </Card>
    </AppLayout>
</template>
