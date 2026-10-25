<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Plus, Search, TrendingUp } from '@lucide/vue';
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
import { formatCurrency, formatDate, formatPercent, label, variant } from '@/composables/format';

const props = defineProps({
    opportunities: { type: Object, required: true },
    filters: { type: Object, required: true },
    pipeline: { type: Object, required: true },
    forecast: { type: Object, required: true },
});

const { can } = usePermissions();
const filters = useFilters('crm.opportunities.index', { ...props.filters, mine: props.filters.mine === '1' || props.filters.mine === true },
    { search: '', stage: 'open', mine: false });

const s = props.pipeline.stages;
const tabs = computed(() => [
    { value: 'open', label: 'En cours', count: props.pipeline.open_count },
    ...['prospecting', 'qualification', 'proposal', 'negotiation'].map((stage) => ({ value: stage, label: label('stage', stage), count: s[stage].count })),
    { value: 'closed_won', label: 'Gagnées', count: s.closed_won.count },
    { value: 'closed_lost', label: 'Perdues', count: s.closed_lost.count },
    { value: 'all', label: 'Toutes', count: null },
]);

const summary = computed(() => [
    { label: 'Opportunités en cours', value: props.pipeline.open_count },
    { label: 'Valeur du pipeline', value: formatCurrency(props.pipeline.open_value) },
    { label: 'Valeur pondérée', value: formatCurrency(props.pipeline.open_weighted_value) },
    { label: 'Taux de réussite', value: formatPercent(props.pipeline.win_rate) },
]);

const monthLabel = (period) => new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric' }).format(new Date(`${period}-01`));
</script>

<template>
    <AppLayout title="Opportunités" :breadcrumbs="[{ title: 'CRM' }, { title: 'Opportunités' }]">
        <PageHeader title="Opportunités" description="Affaires en cours, de la prospection à la signature.">
            <template #actions>
                <Button v-if="can('opportunities.create')" as-child>
                    <Link :href="route('crm.opportunities.create')"><Plus /> Nouvelle opportunité</Link>
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

        <Card>
            <CardHeader>
                <CardTitle>Prévisions</CardTitle>
                <CardDescription>Valeur pondérée attendue par mois de clôture (les retards comptent dans le mois en cours).</CardDescription>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-3">
                <div v-for="period in forecast.periods" :key="period.period" class="rounded-lg border p-4">
                    <p class="text-sm text-muted-foreground capitalize">{{ monthLabel(period.period) }}</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums">{{ formatCurrency(period.weighted_value) }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ period.open_count }} en cours · {{ formatCurrency(period.pipeline_value) }} brut
                        <template v-if="period.won_count"> · {{ formatCurrency(period.won_value) }} gagné</template>
                    </p>
                </div>
            </CardContent>
        </Card>

        <Card class="gap-4 py-4">
            <CardContent class="grid gap-3 px-4">
                <Tabs v-model="filters.stage" class="overflow-x-auto">
                    <TabsList>
                        <TabsTrigger v-for="tab in tabs" :key="tab.value" :value="tab.value">
                            {{ tab.label }} <span v-if="tab.count !== null" class="text-muted-foreground">{{ tab.count }}</span>
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
                        <Label for="mine" class="font-normal">Mes opportunités</Label>
                    </div>
                </div>
            </CardContent>

            <Table v-if="opportunities.data.length">
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Opportunité</TableHead>
                        <TableHead class="hidden md:table-cell">Client</TableHead>
                        <TableHead class="text-right">Valeur</TableHead>
                        <TableHead class="hidden text-right lg:table-cell">Pondérée</TableHead>
                        <TableHead class="hidden sm:table-cell">Clôture</TableHead>
                        <TableHead class="pr-4">Étape</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="o in opportunities.data" :key="o.id">
                        <TableCell class="pl-4">
                            <Link :href="route('crm.opportunities.show', o.id)" class="font-medium hover:underline">{{ o.title }}</Link>
                            <p class="text-xs text-muted-foreground">{{ o.reference }}<template v-if="o.assignee"> · {{ o.assignee.name }}</template></p>
                        </TableCell>
                        <TableCell class="hidden text-muted-foreground md:table-cell">{{ o.customer?.name ?? o.contact?.full_name ?? '—' }}</TableCell>
                        <TableCell class="text-right tabular-nums">{{ formatCurrency(o.value) }}</TableCell>
                        <TableCell class="hidden text-right text-muted-foreground tabular-nums lg:table-cell">{{ formatCurrency(o.weighted_value) }}</TableCell>
                        <TableCell class="hidden text-muted-foreground sm:table-cell">{{ formatDate(o.actual_close_date ?? o.expected_close_date) }}</TableCell>
                        <TableCell class="pr-4"><Badge :variant="variant('stage', o.stage)">{{ label('stage', o.stage) }}</Badge></TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <Empty v-else>
                <EmptyHeader>
                    <EmptyMedia variant="icon"><TrendingUp /></EmptyMedia>
                    <EmptyTitle>Aucune opportunité</EmptyTitle>
                    <EmptyDescription>Aucune opportunité ne correspond à ces filtres.</EmptyDescription>
                </EmptyHeader>
            </Empty>

            <DataPagination :meta="opportunities.meta" class="px-4" />
        </Card>
    </AppLayout>
</template>
