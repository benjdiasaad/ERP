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

const props = defineProps({
    contacts: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const { can } = usePermissions();
const filters = useFilters('crm.contacts.index', props.filters, { search: '', status: '' });
</script>

<template>
    <AppLayout title="Contacts" :breadcrumbs="[{ title: 'CRM' }, { title: 'Contacts' }]">
        <PageHeader title="Contacts" description="Personnes avec qui la société est en relation.">
            <template #actions>
                <Button v-if="can('contacts.create')" as-child>
                    <Link :href="route('crm.contacts.create')"><Plus /> Nouveau contact</Link>
                </Button>
            </template>
        </PageHeader>

        <Card class="gap-4 py-4">
            <CardContent class="flex flex-col gap-3 px-4 sm:flex-row">
                <div class="relative flex-1">
                    <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="filters.search" type="search" placeholder="Nom, e-mail, organisation…" class="pl-8" aria-label="Rechercher" />
                </div>
                <NativeSelect v-model="filters.status" aria-label="Statut" class="sm:w-40">
                    <option value="">Tous</option>
                    <option value="active">Actifs</option>
                    <option value="inactive">Inactifs</option>
                </NativeSelect>
            </CardContent>

            <Table v-if="contacts.data.length">
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Nom</TableHead>
                        <TableHead class="hidden md:table-cell">Organisation</TableHead>
                        <TableHead class="hidden lg:table-cell">E-mail</TableHead>
                        <TableHead class="hidden sm:table-cell">Téléphone</TableHead>
                        <TableHead class="pr-4">Statut</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="contact in contacts.data" :key="contact.id">
                        <TableCell class="pl-4">
                            <Link :href="route('crm.contacts.show', contact.id)" class="font-medium hover:underline">{{ contact.full_name }}</Link>
                            <p v-if="contact.job_title" class="text-xs text-muted-foreground">{{ contact.job_title }}</p>
                        </TableCell>
                        <TableCell class="hidden text-muted-foreground md:table-cell">{{ contact.organization ?? '—' }}</TableCell>
                        <TableCell class="hidden text-muted-foreground lg:table-cell">{{ contact.email ?? '—' }}</TableCell>
                        <TableCell class="hidden text-muted-foreground sm:table-cell">{{ contact.phone ?? contact.mobile ?? '—' }}</TableCell>
                        <TableCell class="pr-4">
                            <Badge :variant="contact.is_active ? 'secondary' : 'outline'">{{ contact.is_active ? 'Actif' : 'Inactif' }}</Badge>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <Empty v-else>
                <EmptyHeader>
                    <EmptyMedia variant="icon"><Users /></EmptyMedia>
                    <EmptyTitle>Aucun contact</EmptyTitle>
                    <EmptyDescription>{{ filters.search || filters.status ? 'Aucun résultat pour ces filtres.' : 'Ajoutez votre premier contact.' }}</EmptyDescription>
                </EmptyHeader>
            </Empty>

            <DataPagination :meta="contacts.meta" class="px-4" />
        </Card>
    </AppLayout>
</template>
