<script setup>
import { Link } from '@inertiajs/vue3';
import { Plus, Search, Warehouse } from '@lucide/vue';
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
import { useFilters } from '@/composables/useFilters';
import { usePermissions } from '@/composables/usePermissions';
import { formatNumber } from '@/composables/format';

const props = defineProps({
    warehouses: { type: Object, required: true },
    totals: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const { can } = usePermissions();
const filters = useFilters('stock.warehouses.index', props.filters, { search: '', status: '' });
</script>

<template>
    <AppLayout title="Entrepôts" :breadcrumbs="[{ title: 'Stock' }, { title: 'Entrepôts' }]">
        <PageHeader title="Entrepôts" description="Lieux de stockage. L'entrepôt par défaut reçoit les réceptions et sert les livraisons.">
            <template #actions>
                <Button v-if="can('warehouses.create')" as-child>
                    <Link :href="route('stock.warehouses.create')"><Plus /> Nouvel entrepôt</Link>
                </Button>
            </template>
        </PageHeader>

        <Card class="gap-4 py-4">
            <CardContent class="flex flex-col gap-3 px-4 sm:flex-row">
                <div class="relative flex-1">
                    <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="filters.search" type="search" placeholder="Nom, code, ville…" class="pl-8" aria-label="Rechercher" />
                </div>
                <NativeSelect v-model="filters.status" aria-label="Statut" class="sm:w-36">
                    <option value="">Tous</option>
                    <option value="active">Actifs</option>
                    <option value="inactive">Inactifs</option>
                </NativeSelect>
            </CardContent>

            <Table v-if="warehouses.data.length">
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Entrepôt</TableHead>
                        <TableHead class="hidden md:table-cell">Ville</TableHead>
                        <TableHead class="text-right">Produits en stock</TableHead>
                        <TableHead class="hidden text-right sm:table-cell">Unités</TableHead>
                        <TableHead class="pr-4">Statut</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="warehouse in warehouses.data" :key="warehouse.id">
                        <TableCell class="pl-4">
                            <Link :href="route('stock.warehouses.show', warehouse.id)" class="font-medium hover:underline">{{ warehouse.name }}</Link>
                            <Badge v-if="warehouse.is_default" variant="outline" class="ml-2">Par défaut</Badge>
                            <p class="text-xs text-muted-foreground">{{ warehouse.code }}</p>
                        </TableCell>
                        <TableCell class="hidden text-muted-foreground md:table-cell">{{ warehouse.city ?? '—' }}</TableCell>
                        <TableCell class="text-right tabular-nums">{{ totals[warehouse.id]?.products ?? 0 }}</TableCell>
                        <TableCell class="hidden text-right tabular-nums sm:table-cell">{{ formatNumber(totals[warehouse.id]?.units) }}</TableCell>
                        <TableCell class="pr-4"><Badge :variant="warehouse.is_active ? 'secondary' : 'outline'">{{ warehouse.is_active ? 'Actif' : 'Inactif' }}</Badge></TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <Empty v-else>
                <EmptyHeader>
                    <EmptyMedia variant="icon"><Warehouse /></EmptyMedia>
                    <EmptyTitle>Aucun entrepôt</EmptyTitle>
                    <EmptyDescription>Aucun entrepôt ne correspond à ces filtres.</EmptyDescription>
                </EmptyHeader>
            </Empty>

            <DataPagination :meta="warehouses.meta" class="px-4" />
        </Card>
    </AppLayout>
</template>
