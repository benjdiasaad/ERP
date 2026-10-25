<script setup>
import { Link } from '@inertiajs/vue3';
import { Building2, Plus, Search } from '@lucide/vue';
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
import { formatCurrency } from '@/composables/format';

const props = defineProps({
    suppliers: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const { can } = usePermissions();
const filters = useFilters('purchasing.suppliers.index', props.filters, { search: '', status: '' });
</script>

<template>
    <AppLayout title="Fournisseurs" :breadcrumbs="[{ title: 'Achats' }, { title: 'Fournisseurs' }]">
        <PageHeader title="Fournisseurs" description="Entreprises auprès desquelles la société achète.">
            <template #actions>
                <Button v-if="can('suppliers.create')" as-child>
                    <Link :href="route('purchasing.suppliers.create')"><Plus /> Nouveau fournisseur</Link>
                </Button>
            </template>
        </PageHeader>

        <Card class="gap-4 py-4">
            <CardContent class="flex flex-col gap-3 px-4 sm:flex-row">
                <div class="relative flex-1">
                    <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="filters.search" type="search" placeholder="Nom, code, ICE, ville…" class="pl-8" aria-label="Rechercher" />
                </div>
                <NativeSelect v-model="filters.status" aria-label="Statut" class="sm:w-36">
                    <option value="">Tous</option>
                    <option value="active">Actifs</option>
                    <option value="inactive">Inactifs</option>
                </NativeSelect>
            </CardContent>

            <Table v-if="suppliers.data.length">
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Fournisseur</TableHead>
                        <TableHead class="hidden md:table-cell">Contact</TableHead>
                        <TableHead class="hidden lg:table-cell">Ville</TableHead>
                        <TableHead class="hidden text-right sm:table-cell">Solde dû</TableHead>
                        <TableHead class="pr-4">Statut</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="supplier in suppliers.data" :key="supplier.id">
                        <TableCell class="pl-4">
                            <Link :href="route('purchasing.suppliers.show', supplier.id)" class="font-medium hover:underline">{{ supplier.name }}</Link>
                            <p class="text-xs text-muted-foreground">{{ supplier.code }}<template v-if="supplier.ice"> · ICE {{ supplier.ice }}</template></p>
                        </TableCell>
                        <TableCell class="hidden text-muted-foreground md:table-cell">
                            <p>{{ supplier.email ?? '—' }}</p>
                            <p v-if="supplier.phone" class="text-xs">{{ supplier.phone }}</p>
                        </TableCell>
                        <TableCell class="hidden text-muted-foreground lg:table-cell">{{ supplier.city ?? '—' }}</TableCell>
                        <TableCell class="hidden text-right tabular-nums sm:table-cell">{{ formatCurrency(supplier.balance) }}</TableCell>
                        <TableCell class="pr-4"><Badge :variant="supplier.is_active ? 'secondary' : 'outline'">{{ supplier.is_active ? 'Actif' : 'Inactif' }}</Badge></TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <Empty v-else>
                <EmptyHeader>
                    <EmptyMedia variant="icon"><Building2 /></EmptyMedia>
                    <EmptyTitle>Aucun fournisseur</EmptyTitle>
                    <EmptyDescription>Aucun fournisseur ne correspond à ces filtres.</EmptyDescription>
                </EmptyHeader>
            </Empty>

            <DataPagination :meta="suppliers.meta" class="px-4" />
        </Card>
    </AppLayout>
</template>
