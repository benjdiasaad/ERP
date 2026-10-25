<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Plus, Search, Target } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import DataPagination from '@/components/DataPagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useFilters } from '@/composables/useFilters';
import { usePermissions } from '@/composables/usePermissions';
import { formatCurrency, formatPercent, label, variant } from '@/composables/format';

const props = defineProps({
    leads: { type: Object, required: true },
    filters: { type: Object, required: true },
    pipeline: { type: Object, required: true },
    statuses: { type: Array, required: true },
});

const { can } = usePermissions();
const filters = useFilters('crm.leads.index', { ...props.filters, mine: props.filters.mine === '1' || props.filters.mine === true }, { search: '', status: '', mine: false });

// Tabs need a non-empty value for "all".
const statusTab = computed({
    get: () => filters.status || 'all',
    set: (value) => (filters.status = value === 'all' ? '' : value),
});

const summary = computed(() => [
    { label: 'Leads ouverts', value: ['new', 'contacted', 'qualified'].reduce((sum, s) => sum + props.pipeline.stages[s].count, 0) },
    { label: 'Valeur estimée ouverte', value: formatCurrency(['new', 'contacted', 'qualified'].reduce((sum, s) => sum + props.pipeline.stages[s].estimated_value, 0)) },
    { label: 'Qualifiés', value: props.pipeline.stages.qualified.count },
    { label: 'Taux de conversion', value: formatPercent(props.pipeline.conversion_rate) },
]);
</script>

<template>
    <AppLayout title="Leads" :breadcrumbs="[{ title: 'CRM' }, { title: 'Leads' }]">
        <PageHeader title="Leads" description="Pistes commerciales à qualifier puis convertir en opportunités.">
            <template #actions>
                <Button v-if="can('leads.create')" as-child>
                    <Link :href="route('crm.leads.create')"><Plus /> Nouveau lead</Link>
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card v-for="item in summary" :key="item.label" class="gap-1 py-4">
                <CardHeader class="px-4">
                    <CardDescription>{{ item.label }}</CardDescription>
                    <CardTitle class="text-xl tabular-nums">{{ item.value }}</CardTitle>
                </CardHeader>
            </Card>
        </div>

        <Card class="gap-4 py-4">
            <CardContent class="grid gap-3 px-4">
                <Tabs v-model="statusTab" class="overflow-x-auto">
                    <TabsList>
                        <TabsTrigger value="all">Tous <span class="text-muted-foreground">{{ pipeline.total_count }}</span></TabsTrigger>
                        <TabsTrigger v-for="status in statuses" :key="status" :value="status">
                            {{ label('leadStatus', status) }} <span class="text-muted-foreground">{{ pipeline.stages[status].count }}</span>
                        </TabsTrigger>
                    </TabsList>
                </Tabs>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="relative flex-1">
                        <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="filters.search" type="search" placeholder="Titre ou référence…" class="pl-8" aria-label="Rechercher" />
                    </div>
                    <div class="flex items-center gap-2">
                        <Checkbox id="mine" v-model="filters.mine" />
                        <Label for="mine" class="font-normal">Mes leads</Label>
                    </div>
                </div>
            </CardContent>

            <Table v-if="leads.data.length">
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Lead</TableHead>
                        <TableHead class="hidden md:table-cell">Contact</TableHead>
                        <TableHead class="hidden text-right sm:table-cell">Valeur estimée</TableHead>
                        <TableHead class="hidden lg:table-cell">Responsable</TableHead>
                        <TableHead class="pr-4">Statut</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="lead in leads.data" :key="lead.id">
                        <TableCell class="pl-4">
                            <Link :href="route('crm.leads.show', lead.id)" class="font-medium hover:underline">{{ lead.title }}</Link>
                            <p class="text-xs text-muted-foreground">{{ lead.reference }} · {{ label('source', lead.source) }}</p>
                        </TableCell>
                        <TableCell class="hidden text-muted-foreground md:table-cell">{{ lead.contact?.full_name ?? '—' }}</TableCell>
                        <TableCell class="hidden text-right tabular-nums sm:table-cell">{{ lead.estimated_value ? formatCurrency(lead.estimated_value) : '—' }}</TableCell>
                        <TableCell class="hidden text-muted-foreground lg:table-cell">{{ lead.assignee?.name ?? '—' }}</TableCell>
                        <TableCell class="pr-4"><Badge :variant="variant('leadStatus', lead.status)">{{ label('leadStatus', lead.status) }}</Badge></TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <Empty v-else>
                <EmptyHeader>
                    <EmptyMedia variant="icon"><Target /></EmptyMedia>
                    <EmptyTitle>Aucun lead</EmptyTitle>
                    <EmptyDescription>Aucun lead ne correspond à ces filtres.</EmptyDescription>
                </EmptyHeader>
            </Empty>

            <DataPagination :meta="leads.meta" class="px-4" />
        </Card>
    </AppLayout>
</template>
