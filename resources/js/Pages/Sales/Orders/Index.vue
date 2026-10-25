<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Plus, Search, ShoppingCart } from '@lucide/vue';
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
    orders: { type: Object, required: true },
    filters: { type: Object, required: true },
    statuses: { type: Array, required: true },
    counts: { type: Object, required: true },
});

const { can } = usePermissions();
const filters = useFilters('sales.orders.index', props.filters, { search: '', status: '', customer_id: '' });
const statusTab = computed({
    get: () => filters.status || 'all',
    set: (value) => (filters.status = value === 'all' ? '' : value),
});
const total = computed(() => Object.values(props.counts).reduce((sum, c) => sum + c.count, 0));
const sum = (...statuses) => statuses.reduce((acc, s) => ({ count: acc.count + props.counts[s].count, amount: acc.amount + props.counts[s].amount }), { count: 0, amount: 0 });

const summary = computed(() => [
    { label: 'Brouillons', ...sum('draft') },
    { label: 'À livrer / facturer', ...sum('confirmed', 'in_progress') },
    { label: 'Livrées', ...sum('delivered') },
    { label: 'Facturées', ...sum('invoiced') },
]);
</script>

<template>
    <AppLayout title="Commandes" :breadcrumbs="[{ title: 'Ventes' }, { title: 'Commandes' }]">
        <PageHeader title="Commandes clients" description="Bons de commande, de la confirmation à la facturation.">
            <template #actions>
                <Button v-if="can('sales_orders.create')" as-child>
                    <Link :href="route('sales.orders.create')"><Plus /> Nouvelle commande</Link>
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card v-for="item in summary" :key="item.label" class="gap-1 py-4">
                <CardHeader class="px-4">
                    <CardDescription>{{ item.label }}</CardDescription>
                    <CardTitle class="text-xl tabular-nums">{{ formatCurrency(item.amount) }}</CardTitle>
                    <p class="text-xs text-muted-foreground">{{ item.count }} commande(s)</p>
                </CardHeader>
            </Card>
        </div>

        <Card class="gap-4 py-4">
            <CardContent class="grid gap-3 px-4">
                <Tabs v-model="statusTab" class="overflow-x-auto">
                    <TabsList>
                        <TabsTrigger value="all">Toutes <span class="text-muted-foreground">{{ total }}</span></TabsTrigger>
                        <TabsTrigger v-for="status in statuses" :key="status" :value="status">
                            {{ label('orderStatus', status) }} <span class="text-muted-foreground">{{ counts[status].count }}</span>
                        </TabsTrigger>
                    </TabsList>
                </Tabs>
                <div class="relative">
                    <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="filters.search" type="search" placeholder="Référence ou client…" class="pl-8" aria-label="Rechercher" />
                </div>
            </CardContent>

            <Table v-if="orders.data.length">
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Commande</TableHead>
                        <TableHead>Client</TableHead>
                        <TableHead class="hidden md:table-cell">Livraison prévue</TableHead>
                        <TableHead class="text-right">Total TTC</TableHead>
                        <TableHead class="pr-4">Statut</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="order in orders.data" :key="order.id">
                        <TableCell class="pl-4">
                            <Link :href="route('sales.orders.show', order.id)" class="font-medium hover:underline">{{ order.reference }}</Link>
                            <p class="text-xs text-muted-foreground">{{ formatDate(order.order_date) }}</p>
                        </TableCell>
                        <TableCell class="max-w-56 truncate">{{ order.customer?.name ?? '—' }}</TableCell>
                        <TableCell class="hidden text-muted-foreground md:table-cell">{{ formatDate(order.expected_delivery_date) }}</TableCell>
                        <TableCell class="text-right tabular-nums">{{ formatMoney(order.total_ttc) }}</TableCell>
                        <TableCell class="pr-4"><Badge :variant="variant('orderStatus', order.status)">{{ label('orderStatus', order.status) }}</Badge></TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <Empty v-else>
                <EmptyHeader>
                    <EmptyMedia variant="icon"><ShoppingCart /></EmptyMedia>
                    <EmptyTitle>Aucune commande</EmptyTitle>
                    <EmptyDescription>Les devis acceptés convertis apparaissent ici.</EmptyDescription>
                </EmptyHeader>
            </Empty>

            <DataPagination :meta="orders.meta" class="px-4" />
        </Card>
    </AppLayout>
</template>
