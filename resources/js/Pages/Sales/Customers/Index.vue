<script setup>
import { Link } from '@inertiajs/vue3';
import { Plus, Search, Users } from '@lucide/vue';
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
import { formatCurrency, label } from '@/composables/format';

const props = defineProps({
    customers: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const { can } = usePermissions();
const filters = useFilters('sales.customers.index', props.filters, { search: '', type: '', status: '' });
</script>

<template>
    <AppLayout title="Clients" :breadcrumbs="[{ title: 'Ventes' }, { title: 'Clients' }]">
        <PageHeader title="Clients" description="Particuliers et entreprises à qui la société vend.">
            <template #actions>
                <Button v-if="can('customers.create')" as-child>
                    <Link :href="route('sales.customers.create')"><Plus /> Nouveau client</Link>
                </Button>
            </template>
        </PageHeader>

        <Card class="gap-4 py-4">
            <CardContent class="flex flex-col gap-3 px-4 sm:flex-row">
                <div class="relative flex-1">
                    <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="filters.search" type="search" placeholder="Nom, code, e-mail, ville…" class="pl-8" aria-label="Rechercher" />
                </div>
                <NativeSelect v-model="filters.type" aria-label="Type" class="sm:w-40">
                    <option value="">Tous les types</option>
                    <option value="company">Entreprises</option>
                    <option value="individual">Particuliers</option>
                </NativeSelect>
                <NativeSelect v-model="filters.status" aria-label="Statut" class="sm:w-36">
                    <option value="">Tous</option>
                    <option value="active">Actifs</option>
                    <option value="inactive">Inactifs</option>
                </NativeSelect>
            </CardContent>

            <Table v-if="customers.data.length">
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Client</TableHead>
                        <TableHead class="hidden md:table-cell">Contact</TableHead>
                        <TableHead class="hidden lg:table-cell">Ville</TableHead>
                        <TableHead class="hidden text-right sm:table-cell">Encours</TableHead>
                        <TableHead class="pr-4">Statut</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="customer in customers.data" :key="customer.id">
                        <TableCell class="pl-4">
                            <Link :href="route('sales.customers.show', customer.id)" class="font-medium hover:underline">{{ customer.name }}</Link>
                            <p class="text-xs text-muted-foreground">{{ customer.code }} · {{ label('customerType', customer.type) }}</p>
                        </TableCell>
                        <TableCell class="hidden text-muted-foreground md:table-cell">
                            <p>{{ customer.email ?? '—' }}</p>
                            <p v-if="customer.phone" class="text-xs">{{ customer.phone }}</p>
                        </TableCell>
                        <TableCell class="hidden text-muted-foreground lg:table-cell">{{ customer.city ?? '—' }}</TableCell>
                        <TableCell class="hidden text-right tabular-nums sm:table-cell">{{ formatCurrency(customer.balance) }}</TableCell>
                        <TableCell class="pr-4">
                            <Badge :variant="customer.is_active ? 'secondary' : 'outline'">{{ customer.is_active ? 'Actif' : 'Inactif' }}</Badge>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <Empty v-else>
                <EmptyHeader>
                    <EmptyMedia variant="icon"><Users /></EmptyMedia>
                    <EmptyTitle>Aucun client</EmptyTitle>
                    <EmptyDescription>Aucun client ne correspond à ces filtres.</EmptyDescription>
                </EmptyHeader>
            </Empty>

            <DataPagination :meta="customers.meta" class="px-4" />
        </Card>
    </AppLayout>
</template>
