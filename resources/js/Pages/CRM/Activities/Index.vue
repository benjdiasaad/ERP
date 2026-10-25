<script setup>
import { Link, router } from '@inertiajs/vue3';
import { ListTodo, Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import DataPagination from '@/components/DataPagination.vue';
import CompleteActivityDialog from '@/components/crm/CompleteActivityDialog.vue';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription,
    AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useFilters } from '@/composables/useFilters';
import { usePermissions } from '@/composables/usePermissions';
import { formatDateTime, label, labels, variant } from '@/composables/format';

const props = defineProps({
    activities: { type: Object, required: true },
    filters: { type: Object, required: true },
    counts: { type: Object, required: true },
});

const { can } = usePermissions();
const filters = useFilters('crm.activities.index', { ...props.filters, mine: props.filters.mine === '1' || props.filters.mine === true },
    { search: '', view: 'todo', type: '', mine: false });

const relatedRoutes = { contact: 'crm.contacts.show', lead: 'crm.leads.show', opportunity: 'crm.opportunities.show' };
const isOpen = (activity) => ['pending', 'in_progress'].includes(activity.status);
const destroy = (activity) => router.delete(route('crm.activities.destroy', activity.id), { preserveScroll: true });
</script>

<template>
    <AppLayout title="Activités" :breadcrumbs="[{ title: 'CRM' }, { title: 'Activités' }]">
        <PageHeader title="Activités" description="Appels, réunions, relances et tâches de l'équipe commerciale.">
            <template #actions>
                <Button v-if="can('activities.create')" as-child>
                    <Link :href="route('crm.activities.create')"><Plus /> Nouvelle activité</Link>
                </Button>
            </template>
        </PageHeader>

        <Card class="gap-4 py-4">
            <CardContent class="grid gap-3 px-4">
                <Tabs v-model="filters.view" class="overflow-x-auto">
                    <TabsList>
                        <TabsTrigger value="todo">À faire</TabsTrigger>
                        <TabsTrigger value="overdue">
                            En retard <Badge v-if="counts.overdue" variant="destructive" class="px-1.5">{{ counts.overdue }}</Badge>
                        </TabsTrigger>
                        <TabsTrigger value="upcoming">Cette semaine</TabsTrigger>
                        <TabsTrigger value="completed">Terminées</TabsTrigger>
                        <TabsTrigger value="all">Toutes</TabsTrigger>
                    </TabsList>
                </Tabs>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="relative flex-1">
                        <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="filters.search" type="search" placeholder="Sujet…" class="pl-8" aria-label="Rechercher" />
                    </div>
                    <NativeSelect v-model="filters.type" aria-label="Type" class="sm:w-40">
                        <option value="">Tous les types</option>
                        <option v-for="(text, value) in labels.activityType" :key="value" :value="value">{{ text }}</option>
                    </NativeSelect>
                    <div class="flex items-center gap-2">
                        <Checkbox id="mine" v-model="filters.mine" />
                        <Label for="mine" class="font-normal">Mes activités</Label>
                    </div>
                </div>
            </CardContent>

            <Table v-if="activities.data.length">
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Activité</TableHead>
                        <TableHead class="hidden md:table-cell">Lié à</TableHead>
                        <TableHead class="hidden sm:table-cell">Échéance</TableHead>
                        <TableHead class="hidden lg:table-cell">Priorité</TableHead>
                        <TableHead>Statut</TableHead>
                        <TableHead class="pr-4"><span class="sr-only">Actions</span></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="activity in activities.data" :key="activity.id">
                        <TableCell class="max-w-72 pl-4">
                            <p class="truncate font-medium">{{ activity.subject }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ label('activityType', activity.type) }}<template v-if="activity.assignee"> · {{ activity.assignee.name }}</template>
                            </p>
                        </TableCell>
                        <TableCell class="hidden max-w-56 md:table-cell">
                            <Link v-if="relatedRoutes[activity.related_type]" :href="route(relatedRoutes[activity.related_type], activity.related_id)"
                                class="block truncate hover:underline">{{ activity.related_label }}</Link>
                            <span v-else class="block truncate text-muted-foreground">{{ activity.related_label ?? '—' }}</span>
                        </TableCell>
                        <TableCell class="hidden whitespace-nowrap sm:table-cell" :class="activity.is_overdue ? 'font-medium text-destructive' : 'text-muted-foreground'">
                            {{ formatDateTime(activity.due_date) }}
                        </TableCell>
                        <TableCell class="hidden lg:table-cell"><Badge :variant="variant('priority', activity.priority)">{{ label('priority', activity.priority) }}</Badge></TableCell>
                        <TableCell><Badge :variant="variant('activityStatus', activity.status)">{{ label('activityStatus', activity.status) }}</Badge></TableCell>
                        <TableCell class="pr-4">
                            <div class="flex justify-end gap-1">
                                <CompleteActivityDialog v-if="isOpen(activity) && can('activities.complete')" :activity="activity" size="xs" />
                                <Button v-if="isOpen(activity) && can('activities.update')" variant="ghost" size="icon-sm" as-child aria-label="Modifier">
                                    <Link :href="route('crm.activities.edit', activity.id)"><Pencil /></Link>
                                </Button>
                                <AlertDialog v-if="can('activities.delete')">
                                    <AlertDialogTrigger as-child>
                                        <Button variant="ghost" size="icon-sm" aria-label="Supprimer"><Trash2 /></Button>
                                    </AlertDialogTrigger>
                                    <AlertDialogContent>
                                        <AlertDialogHeader>
                                            <AlertDialogTitle>Supprimer cette activité ?</AlertDialogTitle>
                                            <AlertDialogDescription>{{ activity.subject }}</AlertDialogDescription>
                                        </AlertDialogHeader>
                                        <AlertDialogFooter>
                                            <AlertDialogCancel>Annuler</AlertDialogCancel>
                                            <AlertDialogAction class="bg-destructive text-white hover:bg-destructive/90" @click="destroy(activity)">Supprimer</AlertDialogAction>
                                        </AlertDialogFooter>
                                    </AlertDialogContent>
                                </AlertDialog>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <Empty v-else>
                <EmptyHeader>
                    <EmptyMedia variant="icon"><ListTodo /></EmptyMedia>
                    <EmptyTitle>Aucune activité</EmptyTitle>
                    <EmptyDescription>Rien à afficher pour cette vue.</EmptyDescription>
                </EmptyHeader>
            </Empty>

            <DataPagination :meta="activities.meta" class="px-4" />
        </Card>
    </AppLayout>
</template>
