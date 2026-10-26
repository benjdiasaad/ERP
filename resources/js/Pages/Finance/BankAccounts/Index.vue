<script setup>
import { Link } from '@inertiajs/vue3';
import { Landmark, Plus, Search } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import DataPagination from '@/components/DataPagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useFilters } from '@/composables/useFilters';
import { usePermissions } from '@/composables/usePermissions';
import { formatAmount } from '@/composables/format';

const props = defineProps({
    accounts: { type: Object, required: true },
    totals: { type: Array, required: true },
    filters: { type: Object, required: true },
});

const { can } = usePermissions();
const filters = useFilters('finance.bank-accounts.index', props.filters, { search: '', status: '' });
</script>

<template>
    <AppLayout title="Comptes bancaires" :breadcrumbs="[{ title: 'Finance' }, { title: 'Comptes bancaires' }]">
        <PageHeader title="Comptes bancaires" description="Comptes de la société et leurs soldes.">
            <template #actions>
                <Button v-if="can('bank_accounts.create')" as-child>
                    <Link :href="route('finance.bank-accounts.create')"><Plus /> Nouveau compte</Link>
                </Button>
            </template>
        </PageHeader>

        <div v-if="totals.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Card v-for="total in totals" :key="total.currency" class="gap-1">
                <CardHeader>
                    <CardDescription>Solde total · {{ total.currency }}</CardDescription>
                    <CardTitle class="text-2xl tabular-nums">{{ formatAmount(total.balance, total.currency) }}</CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-muted-foreground">{{ total.count }} compte{{ total.count > 1 ? 's' : '' }} actif{{ total.count > 1 ? 's' : '' }}</CardContent>
            </Card>
        </div>

        <Card class="gap-4 py-4">
            <CardContent class="flex flex-col gap-3 px-4 sm:flex-row">
                <div class="relative flex-1">
                    <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="filters.search" type="search" placeholder="Nom, banque, n° de compte, IBAN…" class="pl-8" aria-label="Rechercher" />
                </div>
                <NativeSelect v-model="filters.status" aria-label="Statut" class="sm:w-36">
                    <option value="">Tous</option>
                    <option value="active">Actifs</option>
                    <option value="inactive">Inactifs</option>
                </NativeSelect>
            </CardContent>

            <Table v-if="accounts.data.length">
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Compte</TableHead>
                        <TableHead class="hidden md:table-cell">N° de compte</TableHead>
                        <TableHead class="text-right">Solde</TableHead>
                        <TableHead class="pr-4">Statut</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="account in accounts.data" :key="account.id">
                        <TableCell class="pl-4">
                            <Link :href="route('finance.bank-accounts.show', account.id)" class="font-medium hover:underline">{{ account.name }}</Link>
                            <p class="text-xs text-muted-foreground">{{ account.bank }}</p>
                        </TableCell>
                        <TableCell class="hidden font-mono text-xs text-muted-foreground md:table-cell">{{ account.account_number }}</TableCell>
                        <TableCell class="text-right tabular-nums whitespace-nowrap">{{ formatAmount(account.balance, account.currency?.code) }}</TableCell>
                        <TableCell class="pr-4"><Badge :variant="account.is_active ? 'secondary' : 'outline'">{{ account.is_active ? 'Actif' : 'Inactif' }}</Badge></TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <Empty v-else>
                <EmptyHeader>
                    <EmptyMedia variant="icon"><Landmark /></EmptyMedia>
                    <EmptyTitle>Aucun compte bancaire</EmptyTitle>
                    <EmptyDescription>Aucun compte ne correspond à ces filtres.</EmptyDescription>
                </EmptyHeader>
            </Empty>

            <DataPagination :meta="accounts.meta" class="px-4" />
        </Card>
    </AppLayout>
</template>
