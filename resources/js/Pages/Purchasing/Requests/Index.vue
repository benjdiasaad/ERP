<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ClipboardList, Plus, Search } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import DataPagination from '@/components/DataPagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useFilters } from '@/composables/useFilters';
import { usePermissions } from '@/composables/usePermissions';
import { formatDate, formatMoney, label, variant } from '@/composables/format';

const props = defineProps({
    requests: { type: Object, required: true },
    filters: { type: Object, required: true },
    statuses: { type: Array, required: true },
    counts: { type: Object, required: true },
});

const { can } = usePermissions();
const filters = useFilters('purchasing.requests.index', props.filters, { search: '', status: '', priority: '' });
const statusTab = computed({
    get: () => filters.status || 'all',
    set: (value) => (filters.status = value === 'all' ? '' : value),
});
const total = computed(() => Object.values(props.counts).reduce((sum, n) => sum + n, 0));
</script>

<template>
    <AppLayout title="Demandes d'achat" :breadcrumbs="[{ title: 'Achats' }, { title: 'Demandes d\'achat' }]">
        <PageHeader title="Demandes d'achat" description="Besoins internes, de la demande à la commande fournisseur.">
            <template #actions>
                <Button v-if="can('purchase_requests.create')" as-child>
                    <Link :href="route('purchasing.requests.create')"><Plus /> Nouvelle demande</Link>
                </Button>
            </template>
        </PageHeader>

        <Card class="gap-4 py-4">
            <CardContent class="grid gap-3 px-4">
                <Tabs v-model="statusTab" class="overflow-x-auto">
                    <TabsList>
                        <TabsTrigger value="all">Toutes <span class="text-muted-foreground">{{ total }}</span></TabsTrigger>
                        <TabsTrigger v-for="status in statuses" :key="status" :value="status">
                            {{ label('purchaseRequestStatus', status) }} <span class="text-muted-foreground">{{ counts[status] }}</span>
                        </TabsTrigger>
                    </TabsList>
                </Tabs>
                <div class="relative">
                    <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="filters.search" type="search" placeholder="Référence ou objet…" class="pl-8" aria-label="Rechercher" />
                </div>
            </CardContent>

            <Table v-if="requests.data.length">
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Demande</TableHead>
                        <TableHead class="hidden md:table-cell">Priorité</TableHead>
                        <TableHead class="hidden lg:table-cell">Souhaitée pour</TableHead>
                        <TableHead class="hidden text-right sm:table-cell">Estimation HT</TableHead>
                        <TableHead class="pr-4">Statut</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="request in requests.data" :key="request.id">
                        <TableCell class="max-w-80 pl-4">
                            <Link :href="route('purchasing.requests.show', request.id)" class="block truncate font-medium hover:underline">{{ request.title }}</Link>
                            <p class="text-xs text-muted-foreground">{{ request.reference }}</p>
                        </TableCell>
                        <TableCell class="hidden md:table-cell"><Badge :variant="variant('priority', request.priority)">{{ label('priority', request.priority) }}</Badge></TableCell>
                        <TableCell class="hidden text-muted-foreground lg:table-cell">{{ formatDate(request.required_date) }}</TableCell>
                        <TableCell class="hidden text-right tabular-nums sm:table-cell">{{ formatMoney(request.estimated_total) }}</TableCell>
                        <TableCell class="pr-4"><Badge :variant="variant('purchaseRequestStatus', request.status)">{{ label('purchaseRequestStatus', request.status) }}</Badge></TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <Empty v-else>
                <EmptyHeader>
                    <EmptyMedia variant="icon"><ClipboardList /></EmptyMedia>
                    <EmptyTitle>Aucune demande</EmptyTitle>
                    <EmptyDescription>Aucune demande d'achat ne correspond à ces filtres.</EmptyDescription>
                </EmptyHeader>
            </Empty>

            <DataPagination :meta="requests.meta" class="px-4" />
        </Card>
    </AppLayout>
</template>
