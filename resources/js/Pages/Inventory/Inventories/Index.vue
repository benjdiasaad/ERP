<script setup>
import { Link } from '@inertiajs/vue3';
import { ClipboardCheck, Plus, Search } from '@lucide/vue';
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
import { formatDate, formatDateTime, label, variant } from '@/composables/format';

const props = defineProps({
    inventories: { type: Object, required: true },
    counts: { type: Object, required: true },
    filters: { type: Object, required: true },
    warehouses: { type: Array, required: true },
});

const { can } = usePermissions();
const filters = useFilters('stock.inventories.index', props.filters, { search: '', status: '', warehouse_id: '' });
const statuses = ['draft', 'in_progress', 'validated', 'cancelled'];
</script>

<template>
    <AppLayout title="Inventaires" :breadcrumbs="[{ title: 'Stock' }, { title: 'Inventaires' }]">
        <PageHeader title="Inventaires" description="Comptages physiques : à la validation, le stock est ajusté sur les quantités comptées.">
            <template #actions>
                <Button v-if="can('stock_inventories.create')" as-child>
                    <Link :href="route('stock.inventories.create')"><Plus /> Nouvel inventaire</Link>
                </Button>
            </template>
        </PageHeader>

        <Tabs v-model="filters.status" class="overflow-x-auto">
            <TabsList>
                <TabsTrigger value="">Tous</TabsTrigger>
                <TabsTrigger v-for="s in statuses" :key="s" :value="s">
                    {{ label('inventoryStatus', s) }}<span v-if="counts[s]" class="ml-1 text-muted-foreground tabular-nums">{{ counts[s] }}</span>
                </TabsTrigger>
            </TabsList>
        </Tabs>

        <Card class="gap-4 py-4">
            <CardContent class="flex flex-col gap-3 px-4 sm:flex-row">
                <div class="relative flex-1">
                    <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="filters.search" type="search" placeholder="Référence…" class="pl-8" aria-label="Rechercher" />
                </div>
                <NativeSelect v-model="filters.warehouse_id" aria-label="Entrepôt" class="sm:w-52">
                    <option value="">Tous les entrepôts</option>
                    <option v-for="w in warehouses" :key="w.value" :value="w.value">{{ w.label }}</option>
                </NativeSelect>
            </CardContent>

            <Table v-if="inventories.data.length">
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Référence</TableHead>
                        <TableHead class="hidden sm:table-cell">Entrepôt</TableHead>
                        <TableHead class="text-right">Produits</TableHead>
                        <TableHead class="hidden md:table-cell">Validé le</TableHead>
                        <TableHead class="pr-4">Statut</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="inventory in inventories.data" :key="inventory.id">
                        <TableCell class="pl-4">
                            <Link :href="route('stock.inventories.show', inventory.id)" class="font-medium hover:underline">{{ inventory.reference }}</Link>
                            <p class="text-xs text-muted-foreground">{{ formatDate(inventory.count_date) }}</p>
                        </TableCell>
                        <TableCell class="hidden text-muted-foreground sm:table-cell">{{ inventory.warehouse?.name ?? '—' }}</TableCell>
                        <TableCell class="text-right tabular-nums">{{ inventory.lines_count }}</TableCell>
                        <TableCell class="hidden text-muted-foreground md:table-cell">{{ formatDateTime(inventory.validated_at) }}</TableCell>
                        <TableCell class="pr-4"><Badge :variant="variant('inventoryStatus', inventory.status)">{{ label('inventoryStatus', inventory.status) }}</Badge></TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <Empty v-else>
                <EmptyHeader>
                    <EmptyMedia variant="icon"><ClipboardCheck /></EmptyMedia>
                    <EmptyTitle>Aucun inventaire</EmptyTitle>
                    <EmptyDescription>Aucun inventaire ne correspond à ces filtres.</EmptyDescription>
                </EmptyHeader>
            </Empty>

            <DataPagination :meta="inventories.meta" class="px-4" />
        </Card>
    </AppLayout>
</template>
