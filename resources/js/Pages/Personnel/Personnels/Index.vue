<script setup>
import { Link } from '@inertiajs/vue3';
import { Plus, Search, UserCog } from '@lucide/vue';
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
import { formatDate, label, labels, variant } from '@/composables/format';

const props = defineProps({
    personnels: { type: Object, required: true },
    departments: { type: Array, required: true },
    filters: { type: Object, required: true },
});

const { can } = usePermissions();
const filters = useFilters('organisation.personnels.index', props.filters, { search: '', department_id: '', status: '' });
</script>

<template>
    <AppLayout title="Personnel" :breadcrumbs="[{ title: 'Organisation' }, { title: 'Personnel' }]">
        <PageHeader title="Personnel" description="Fiches des collaborateurs de la société.">
            <template #actions>
                <Button v-if="can('personnels.create')" as-child>
                    <Link :href="route('organisation.personnels.create')"><Plus /> Nouveau collaborateur</Link>
                </Button>
            </template>
        </PageHeader>

        <Card class="gap-4 py-4">
            <CardContent class="flex flex-col gap-3 px-4 sm:flex-row">
                <div class="relative flex-1">
                    <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="filters.search" type="search" placeholder="Nom, matricule, e-mail…" class="pl-8" aria-label="Rechercher" />
                </div>
                <NativeSelect v-model="filters.department_id" aria-label="Département" class="sm:w-48">
                    <option value="">Tous les départements</option>
                    <option v-for="d in departments" :key="d.value" :value="d.value">{{ d.label }}</option>
                </NativeSelect>
                <NativeSelect v-model="filters.status" aria-label="Statut" class="sm:w-36">
                    <option value="">Tous statuts</option>
                    <option v-for="(text, value) in labels.personnelStatus" :key="value" :value="value">{{ text }}</option>
                </NativeSelect>
            </CardContent>

            <Table v-if="personnels.data.length">
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Collaborateur</TableHead>
                        <TableHead class="hidden md:table-cell">Poste</TableHead>
                        <TableHead class="hidden lg:table-cell">Embauche</TableHead>
                        <TableHead class="pr-4">Statut</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="p in personnels.data" :key="p.id">
                        <TableCell class="pl-4">
                            <Link :href="route('organisation.personnels.show', p.id)" class="font-medium hover:underline">{{ p.last_name }} {{ p.first_name }}</Link>
                            <p class="text-xs text-muted-foreground">{{ p.matricule }}<template v-if="p.email"> · {{ p.email }}</template></p>
                        </TableCell>
                        <TableCell class="hidden md:table-cell">
                            <p>{{ p.position?.name ?? '—' }}</p>
                            <p class="text-xs text-muted-foreground">{{ p.department?.name }}</p>
                        </TableCell>
                        <TableCell class="hidden text-muted-foreground lg:table-cell">{{ formatDate(p.hire_date) }}</TableCell>
                        <TableCell class="pr-4"><Badge :variant="variant('personnelStatus', p.status)">{{ label('personnelStatus', p.status) }}</Badge></TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <Empty v-else>
                <EmptyHeader>
                    <EmptyMedia variant="icon"><UserCog /></EmptyMedia>
                    <EmptyTitle>Aucun collaborateur</EmptyTitle>
                    <EmptyDescription>Aucune fiche ne correspond à ces filtres.</EmptyDescription>
                </EmptyHeader>
            </Empty>

            <DataPagination :meta="personnels.meta" class="px-4" />
        </Card>
    </AppLayout>
</template>
