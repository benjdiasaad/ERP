<script setup>
import { Link } from '@inertiajs/vue3';
import { Boxes, Search, TriangleAlert } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import DataPagination from '@/components/DataPagination.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useFilters } from '@/composables/useFilters';
import { formatDateTime, formatNumber } from '@/composables/format';

const props = defineProps({
    levels: { type: Object, required: true },
    filters: { type: Object, required: true },
    warehouses: { type: Array, required: true },
});

const filters = useFilters('stock.levels.index', { ...props.filters, low: !!props.filters.low }, { search: '', warehouse_id: '', low: false });
</script>

<template>
    <AppLayout title="État du stock" :breadcrumbs="[{ title: 'Stock' }, { title: 'État du stock' }]">
        <PageHeader title="État du stock" description="Quantités disponibles par produit et par entrepôt." />

        <Card class="gap-4 py-4">
            <CardContent class="flex flex-col gap-3 px-4 sm:flex-row sm:items-center">
                <div class="relative flex-1">
                    <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="filters.search" type="search" placeholder="Produit ou code…" class="pl-8" aria-label="Rechercher" />
                </div>
                <NativeSelect v-model="filters.warehouse_id" aria-label="Entrepôt" class="sm:w-52">
                    <option value="">Tous les entrepôts</option>
                    <option v-for="w in warehouses" :key="w.value" :value="w.value">{{ w.label }}</option>
                </NativeSelect>
                <div class="flex items-center gap-2">
                    <Checkbox id="low" v-model="filters.low" />
                    <Label for="low" class="font-normal whitespace-nowrap">Stock bas uniquement</Label>
                </div>
            </CardContent>

            <Table v-if="levels.data.length">
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Produit</TableHead>
                        <TableHead class="hidden md:table-cell">Entrepôt</TableHead>
                        <TableHead class="text-right">En stock</TableHead>
                        <TableHead class="hidden text-right sm:table-cell">Réservé</TableHead>
                        <TableHead class="hidden text-right sm:table-cell">Disponible</TableHead>
                        <TableHead class="hidden text-right lg:table-cell">Point de cde</TableHead>
                        <TableHead class="hidden pr-4 lg:table-cell">Dernier mouvement</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="level in levels.data" :key="level.id">
                        <TableCell class="pl-4">
                            <Link v-if="level.product" :href="route('stock.products.show', level.product.id)" class="font-medium hover:underline">{{ level.product.name }}</Link>
                            <p class="text-xs text-muted-foreground">{{ level.product?.code }}<span class="md:hidden"> · {{ level.warehouse?.name }}</span></p>
                        </TableCell>
                        <TableCell class="hidden md:table-cell">
                            <Link v-if="level.warehouse" :href="route('stock.warehouses.show', level.warehouse.id)" class="text-muted-foreground hover:underline">{{ level.warehouse.name }}</Link>
                        </TableCell>
                        <TableCell class="text-right tabular-nums whitespace-nowrap" :class="level.low ? 'font-medium text-destructive' : ''">
                            <TriangleAlert v-if="level.low" class="mr-1 inline size-3.5" aria-label="Stock bas" />
                            {{ formatNumber(level.on_hand) }} <span class="text-xs text-muted-foreground">{{ level.product?.unit }}</span>
                        </TableCell>
                        <TableCell class="hidden text-right tabular-nums text-muted-foreground sm:table-cell">{{ formatNumber(level.reserved) }}</TableCell>
                        <TableCell class="hidden text-right tabular-nums sm:table-cell">{{ formatNumber(level.available) }}</TableCell>
                        <TableCell class="hidden text-right tabular-nums text-muted-foreground lg:table-cell">{{ level.reorder_point ? formatNumber(level.reorder_point) : '—' }}</TableCell>
                        <TableCell class="hidden pr-4 text-muted-foreground lg:table-cell">{{ formatDateTime(level.last_movement_at) }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <Empty v-else>
                <EmptyHeader>
                    <EmptyMedia variant="icon"><Boxes /></EmptyMedia>
                    <EmptyTitle>Aucun stock</EmptyTitle>
                    <EmptyDescription>Les quantités apparaissent après une réception, un mouvement ou un inventaire.</EmptyDescription>
                </EmptyHeader>
            </Empty>

            <DataPagination :meta="levels" class="px-4" />
        </Card>
    </AppLayout>
</template>
