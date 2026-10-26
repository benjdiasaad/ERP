<script setup>
import { Link } from '@inertiajs/vue3';
import { BookOpen, Plus, Search } from '@lucide/vue';
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
import { formatAmount, formatDate, label, variant } from '@/composables/format';

const props = defineProps({
    entries: { type: Object, required: true },
    counts: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const { can } = usePermissions();
const filters = useFilters('finance.journal-entries.index', props.filters, { search: '', status: '', from: '', to: '' });
</script>

<template>
    <AppLayout title="Écritures" :breadcrumbs="[{ title: 'Finance' }, { title: 'Écritures' }]">
        <PageHeader title="Écritures comptables" description="Journal des opérations : une écriture comptabilisée n'est plus modifiable.">
            <template #actions>
                <Button v-if="can('journal_entries.create')" as-child>
                    <Link :href="route('finance.journal-entries.create')"><Plus /> Nouvelle écriture</Link>
                </Button>
            </template>
        </PageHeader>

        <Tabs v-model="filters.status" class="overflow-x-auto">
            <TabsList>
                <TabsTrigger value="">Toutes</TabsTrigger>
                <TabsTrigger v-for="(count, status) in counts" :key="status" :value="status">
                    {{ label('journalStatus', status) }}<span v-if="count" class="ml-1 text-muted-foreground tabular-nums">{{ count }}</span>
                </TabsTrigger>
            </TabsList>
        </Tabs>

        <Card class="gap-4 py-4">
            <CardContent class="flex flex-col gap-3 px-4 sm:flex-row">
                <div class="relative flex-1">
                    <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="filters.search" type="search" placeholder="Référence ou libellé…" class="pl-8" aria-label="Rechercher" />
                </div>
                <div class="grid grid-cols-2 gap-3 sm:flex">
                    <Input v-model="filters.from" type="date" aria-label="Du" class="sm:w-40" />
                    <Input v-model="filters.to" type="date" aria-label="Au" class="sm:w-40" />
                </div>
            </CardContent>

            <Table v-if="entries.data.length">
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Écriture</TableHead>
                        <TableHead class="hidden md:table-cell">Libellé</TableHead>
                        <TableHead class="text-right">Montant</TableHead>
                        <TableHead class="pr-4">Statut</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="entry in entries.data" :key="entry.id">
                        <TableCell class="pl-4">
                            <Link :href="route('finance.journal-entries.show', entry.id)" class="font-medium hover:underline">{{ entry.reference }}</Link>
                            <p class="text-xs text-muted-foreground">{{ formatDate(entry.date) }}</p>
                        </TableCell>
                        <TableCell class="hidden max-w-md truncate text-muted-foreground md:table-cell">{{ entry.description ?? '—' }}</TableCell>
                        <TableCell class="text-right tabular-nums whitespace-nowrap">
                            {{ formatAmount(entry.total_debit) }}
                            <p v-if="entry.total_debit !== entry.total_credit" class="text-xs text-destructive">Déséquilibrée</p>
                        </TableCell>
                        <TableCell class="pr-4"><Badge :variant="variant('journalStatus', entry.status)">{{ label('journalStatus', entry.status) }}</Badge></TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <Empty v-else>
                <EmptyHeader>
                    <EmptyMedia variant="icon"><BookOpen /></EmptyMedia>
                    <EmptyTitle>Aucune écriture</EmptyTitle>
                    <EmptyDescription>Aucune écriture ne correspond à ces filtres.</EmptyDescription>
                </EmptyHeader>
            </Empty>

            <DataPagination :meta="entries.meta" class="px-4" />
        </Card>
    </AppLayout>
</template>
