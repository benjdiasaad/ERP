<script setup>
import { ArrowLeftRight, Plus, Search } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import DataPagination from '@/components/DataPagination.vue';
import MovementDialog from '@/components/inventory/MovementDialog.vue';
import MovementsTable from '@/components/inventory/MovementsTable.vue';
import TransferDialog from '@/components/inventory/TransferDialog.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useFilters } from '@/composables/useFilters';
import { usePermissions } from '@/composables/usePermissions';
import { labels } from '@/composables/format';

const props = defineProps({
    movements: { type: Object, required: true },
    filters: { type: Object, required: true },
    warehouses: { type: Array, required: true },
    products: { type: Array, required: true },
});

const { can } = usePermissions();
const filters = useFilters('stock.movements.index', props.filters, { search: '', warehouse_id: '', type: '', from: '', to: '' });
</script>

<template>
    <AppLayout title="Mouvements de stock" :breadcrumbs="[{ title: 'Stock' }, { title: 'Mouvements' }]">
        <PageHeader title="Mouvements de stock" description="Journal des entrées, sorties, transferts et ajustements. Un mouvement ne se modifie pas : corrigez par un nouveau mouvement.">
            <template #actions>
                <TransferDialog v-if="can('stock_movements.transfer') && warehouses.length > 1" :products="products" :warehouses="warehouses">
                    <ArrowLeftRight /> Transfert
                </TransferDialog>
                <MovementDialog v-if="can('stock_movements.create')" :products="products" :warehouses="warehouses">
                    <Plus /> Nouveau mouvement
                </MovementDialog>
            </template>
        </PageHeader>

        <Card class="gap-4 py-4">
            <CardContent class="flex flex-col gap-3 px-4 lg:flex-row">
                <div class="relative flex-1">
                    <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="filters.search" type="search" placeholder="Produit, code, référence…" class="pl-8" aria-label="Rechercher" />
                </div>
                <div class="grid grid-cols-2 gap-3 sm:flex">
                    <NativeSelect v-model="filters.warehouse_id" aria-label="Entrepôt" class="w-full sm:w-44">
                        <option value="">Tous les entrepôts</option>
                        <option v-for="w in warehouses" :key="w.value" :value="w.value">{{ w.label }}</option>
                    </NativeSelect>
                    <NativeSelect v-model="filters.type" aria-label="Type" class="w-full sm:w-36">
                        <option value="">Tous types</option>
                        <option v-for="(text, value) in labels.movementType" :key="value" :value="value">{{ text }}</option>
                    </NativeSelect>
                    <Input v-model="filters.from" type="date" aria-label="Du" class="sm:w-38" />
                    <Input v-model="filters.to" type="date" aria-label="Au" class="sm:w-38" />
                </div>
            </CardContent>

            <MovementsTable v-if="movements.data.length" :movements="movements.data" />

            <Empty v-else>
                <EmptyHeader>
                    <EmptyMedia variant="icon"><ArrowLeftRight /></EmptyMedia>
                    <EmptyTitle>Aucun mouvement</EmptyTitle>
                    <EmptyDescription>Les réceptions, livraisons, inventaires et saisies manuelles apparaissent ici.</EmptyDescription>
                </EmptyHeader>
            </Empty>

            <DataPagination :meta="movements.meta" class="px-4" />
        </Card>
    </AppLayout>
</template>
