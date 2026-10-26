<script setup>
import { Link } from '@inertiajs/vue3';
import { Plus, Search, ShieldCheck, TriangleAlert } from '@lucide/vue';
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
import { NativeSelect } from '@/components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useFilters } from '@/composables/useFilters';
import { usePermissions } from '@/composables/usePermissions';
import { formatAmount, formatDate, label, variant } from '@/composables/format';

const props = defineProps({
    cautions: { type: Object, required: true },
    counts: { type: Object, required: true },
    stats: { type: Object, required: true },
    types: { type: Array, required: true },
    filters: { type: Object, required: true },
});

const { can } = usePermissions();
const filters = useFilters('finance.cautions.index', { ...props.filters, expiring: !!props.filters.expiring },
    { search: '', status: '', direction: '', caution_type_id: '', expiring: false });
const tabs = ['draft', 'active', 'partially_returned', 'returned', 'forfeited'];

// Held cautions expiring within 30 days.
const soon = (c) => ['active', 'partially_returned'].includes(c.status)
    && (new Date(c.expiry_date) - new Date()) / 86400000 <= 30;
</script>

<template>
    <AppLayout title="Cautions" :breadcrumbs="[{ title: 'Finance' }, { title: 'Cautions' }]">
        <PageHeader title="Cautions" description="Garanties données aux clients et reçues des fournisseurs, jusqu'à leur restitution.">
            <template #actions>
                <Button v-if="can('cautions.create')" as-child>
                    <Link :href="route('finance.cautions.create')"><Plus /> Nouvelle caution</Link>
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-3">
            <Card class="gap-1">
                <CardHeader><CardDescription>Cautions données en cours</CardDescription><CardTitle class="text-2xl tabular-nums">{{ formatAmount(stats.given) }}</CardTitle></CardHeader>
            </Card>
            <Card class="gap-1">
                <CardHeader><CardDescription>Cautions reçues en cours</CardDescription><CardTitle class="text-2xl tabular-nums">{{ formatAmount(stats.received) }}</CardTitle></CardHeader>
            </Card>
            <Card class="gap-1">
                <CardHeader>
                    <CardDescription>Échéance dans les 30 jours</CardDescription>
                    <CardTitle class="text-2xl tabular-nums" :class="stats.expiring ? 'text-destructive' : ''">{{ stats.expiring }}</CardTitle>
                </CardHeader>
            </Card>
        </div>

        <Tabs v-model="filters.status" class="overflow-x-auto">
            <TabsList>
                <TabsTrigger value="">Toutes</TabsTrigger>
                <TabsTrigger v-for="s in tabs" :key="s" :value="s">
                    {{ label('cautionStatus', s) }}<span v-if="counts[s]" class="ml-1 text-muted-foreground tabular-nums">{{ counts[s] }}</span>
                </TabsTrigger>
            </TabsList>
        </Tabs>

        <Card class="gap-4 py-4">
            <CardContent class="flex flex-col gap-3 px-4 lg:flex-row lg:items-center">
                <div class="relative flex-1">
                    <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="filters.search" type="search" placeholder="Partenaire, référence, banque…" class="pl-8" aria-label="Rechercher" />
                </div>
                <div class="grid grid-cols-2 gap-3 sm:flex sm:items-center">
                    <NativeSelect v-model="filters.direction" aria-label="Sens" class="w-full sm:w-32">
                        <option value="">Données et reçues</option>
                        <option value="given">Données</option>
                        <option value="received">Reçues</option>
                    </NativeSelect>
                    <NativeSelect v-model="filters.caution_type_id" aria-label="Type" class="w-full sm:w-44">
                        <option value="">Tous types</option>
                        <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
                    </NativeSelect>
                    <div class="col-span-2 flex items-center gap-2">
                        <Checkbox id="expiring" v-model="filters.expiring" />
                        <Label for="expiring" class="font-normal whitespace-nowrap">Échéance proche</Label>
                    </div>
                </div>
            </CardContent>

            <Table v-if="cautions.data.length">
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Caution</TableHead>
                        <TableHead class="hidden md:table-cell">Partenaire</TableHead>
                        <TableHead class="text-right">Montant</TableHead>
                        <TableHead class="hidden sm:table-cell">Échéance</TableHead>
                        <TableHead class="pr-4">Statut</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="caution in cautions.data" :key="caution.id">
                        <TableCell class="pl-4">
                            <Link :href="route('finance.cautions.show', caution.id)" class="font-medium hover:underline">
                                {{ caution.caution_type?.name ?? 'Caution' }}
                            </Link>
                            <p class="text-xs text-muted-foreground">
                                {{ label('cautionDirection', caution.direction) }}<template v-if="caution.document_reference"> · {{ caution.document_reference }}</template>
                            </p>
                        </TableCell>
                        <TableCell class="hidden md:table-cell">
                            <p>{{ caution.partner?.name ?? '—' }}</p>
                            <p class="text-xs text-muted-foreground">{{ label('cautionPartner', caution.partner_alias) }}</p>
                        </TableCell>
                        <TableCell class="text-right tabular-nums whitespace-nowrap">
                            {{ formatAmount(caution.amount, caution.currency) }}
                            <p v-if="caution.status === 'partially_returned'" class="text-xs text-muted-foreground">reste {{ formatAmount(caution.remaining, caution.currency) }}</p>
                        </TableCell>
                        <TableCell class="hidden whitespace-nowrap sm:table-cell" :class="soon(caution) ? 'font-medium text-destructive' : 'text-muted-foreground'">
                            <TriangleAlert v-if="soon(caution)" class="mr-1 inline size-3.5" aria-label="Échéance proche" />{{ formatDate(caution.expiry_date) }}
                        </TableCell>
                        <TableCell class="pr-4"><Badge :variant="variant('cautionStatus', caution.status)">{{ label('cautionStatus', caution.status) }}</Badge></TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <Empty v-else>
                <EmptyHeader>
                    <EmptyMedia variant="icon"><ShieldCheck /></EmptyMedia>
                    <EmptyTitle>Aucune caution</EmptyTitle>
                    <EmptyDescription>Aucune caution ne correspond à ces filtres.</EmptyDescription>
                </EmptyHeader>
            </Empty>

            <DataPagination :meta="cautions.meta" class="px-4" />
        </Card>
    </AppLayout>
</template>
