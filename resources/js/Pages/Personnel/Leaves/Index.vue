<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { Check, Plane, Plus, Search, Trash2, X } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import DataPagination from '@/components/DataPagination.vue';
import FormField from '@/components/FormField.vue';
import ReasonDialog from '@/components/ReasonDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useAction } from '@/composables/useAction';
import { useFilters } from '@/composables/useFilters';
import { usePermissions } from '@/composables/usePermissions';
import { formatDate, label, labels, variant } from '@/composables/format';

const props = defineProps({
    leaves: { type: Object, required: true },
    counts: { type: Object, required: true },
    personnels: { type: Array, required: true },
    filters: { type: Object, required: true },
});

const { can } = usePermissions();
const filters = useFilters('organisation.leaves.index', props.filters, { search: '', status: '', leave_type: '' });
const typeOptions = Object.entries(labels.leaveType).map(([value, text]) => ({ value, label: text }));
const { run, processing } = useAction();

const open = ref(false);
const today = new Date().toISOString().slice(0, 10);
const form = useForm({ personnel_id: null, type: 'annual', start_date: today, end_date: today, reason: '' });
const submit = () => form.post(route('organisation.leaves.store'), { preserveScroll: true, onSuccess: () => { open.value = false; form.reset(); } });
</script>

<template>
    <AppLayout title="Congés" :breadcrumbs="[{ title: 'Organisation' }, { title: 'Congés' }]">
        <PageHeader title="Congés" description="Demandes de congé du personnel et leur validation.">
            <template #actions>
                <Dialog v-if="can('leaves.create')" v-model:open="open">
                    <DialogTrigger as-child><Button><Plus /> Nouvelle demande</Button></DialogTrigger>
                    <DialogContent>
                        <DialogHeader><DialogTitle>Nouvelle demande de congé</DialogTitle></DialogHeader>
                        <form class="grid gap-4" @submit.prevent="submit">
                            <FormField id="leave-personnel" v-model="form.personnel_id" label="Collaborateur" :options="personnels" placeholder="Choisir…" required :error="form.errors.personnel_id" />
                            <FormField id="leave-type" v-model="form.type" label="Type" :options="typeOptions" required :error="form.errors.type" />
                            <div class="grid gap-4 sm:grid-cols-2">
                                <FormField id="leave-start" v-model="form.start_date" label="Du" type="date" required :error="form.errors.start_date" />
                                <FormField id="leave-end" v-model="form.end_date" label="Au (inclus)" type="date" required :error="form.errors.end_date" />
                            </div>
                            <FormField id="leave-reason" v-model="form.reason" label="Motif" type="textarea" :error="form.errors.reason" />
                            <DialogFooter><Button type="submit" :disabled="form.processing">Enregistrer</Button></DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </template>
        </PageHeader>

        <Tabs v-model="filters.status" class="overflow-x-auto">
            <TabsList>
                <TabsTrigger value="">Toutes</TabsTrigger>
                <TabsTrigger v-for="(count, status) in counts" :key="status" :value="status">
                    {{ label('leaveStatus', status) }}<span v-if="count" class="ml-1 text-muted-foreground tabular-nums">{{ count }}</span>
                </TabsTrigger>
            </TabsList>
        </Tabs>

        <Card class="gap-4 py-4">
            <CardContent class="flex flex-col gap-3 px-4 sm:flex-row">
                <div class="relative flex-1">
                    <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="filters.search" type="search" placeholder="Nom ou matricule…" class="pl-8" aria-label="Rechercher" />
                </div>
                <NativeSelect v-model="filters.leave_type" aria-label="Type" class="sm:w-44">
                    <option value="">Tous types</option>
                    <option v-for="t in typeOptions" :key="t.value" :value="t.value">{{ t.label }}</option>
                </NativeSelect>
            </CardContent>

            <Table v-if="leaves.data.length">
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Collaborateur</TableHead>
                        <TableHead>Période</TableHead>
                        <TableHead class="hidden text-right sm:table-cell">Jours</TableHead>
                        <TableHead>Statut</TableHead>
                        <TableHead class="pr-4"><span class="sr-only">Actions</span></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="leave in leaves.data" :key="leave.id">
                        <TableCell class="pl-4">
                            <p class="font-medium">{{ leave.personnel?.last_name }} {{ leave.personnel?.first_name }}</p>
                            <p class="text-xs text-muted-foreground">{{ label('leaveType', leave.leave_type) }}</p>
                        </TableCell>
                        <TableCell class="whitespace-nowrap">
                            {{ formatDate(leave.start_date) }} → {{ formatDate(leave.end_date) }}
                            <p v-if="leave.reason" class="max-w-xs truncate text-xs text-muted-foreground">{{ leave.reason }}</p>
                        </TableCell>
                        <TableCell class="hidden text-right tabular-nums sm:table-cell">{{ Number(leave.total_days) }}</TableCell>
                        <TableCell>
                            <Badge :variant="variant('leaveStatus', leave.status)">{{ label('leaveStatus', leave.status) }}</Badge>
                            <p v-if="leave.rejection_reason" class="mt-1 max-w-xs truncate text-xs text-muted-foreground">{{ leave.rejection_reason }}</p>
                        </TableCell>
                        <TableCell class="pr-4 text-right whitespace-nowrap">
                            <ReasonDialog v-if="leave.status === 'approved' && can('leaves.reject')" :url="route('organisation.leaves.reject', leave.id)" title="Révoquer le congé approuvé"
                                field-label="Motif" confirm-label="Révoquer" trigger-variant="ghost"><X /><span class="sr-only">Révoquer</span></ReasonDialog>
                            <template v-if="leave.status === 'pending'">
                                <Button v-if="can('leaves.approve')" variant="ghost" size="icon" :aria-label="`Approuver le congé de ${leave.personnel?.first_name}`" :disabled="processing"
                                    @click="run(route('organisation.leaves.approve', leave.id))"><Check /></Button>
                                <ReasonDialog v-if="can('leaves.reject')" :url="route('organisation.leaves.reject', leave.id)" title="Refuser le congé"
                                    field-label="Motif du refus" confirm-label="Refuser" trigger-variant="ghost"><X /><span class="sr-only">Refuser</span></ReasonDialog>
                            </template>
                            <Button v-if="['pending', 'cancelled', 'rejected'].includes(leave.status) && can('leaves.delete')" variant="ghost" size="icon"
                                :aria-label="`Supprimer la demande`" @click="router.delete(route('organisation.leaves.destroy', leave.id), { preserveScroll: true })"><Trash2 /></Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <Empty v-else>
                <EmptyHeader>
                    <EmptyMedia variant="icon"><Plane /></EmptyMedia>
                    <EmptyTitle>Aucune demande</EmptyTitle>
                    <EmptyDescription>Aucune demande de congé ne correspond à ces filtres.</EmptyDescription>
                </EmptyHeader>
            </Empty>

            <DataPagination :meta="leaves.meta" class="px-4" />
        </Card>
    </AppLayout>
</template>
