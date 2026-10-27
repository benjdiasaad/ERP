<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { Network, Pencil, Plus, Trash2 } from '@lucide/vue';
import { toast } from 'vue-sonner';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import FormField from '@/components/FormField.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardAction, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { usePermissions } from '@/composables/usePermissions';
import { formatAmount } from '@/composables/format';

const props = defineProps({
    departments: { type: Array, required: true },
    positions: { type: Array, required: true },
    managers: { type: Array, required: true },
});

const { can } = usePermissions();
const departmentOptions = computed(() => props.departments.map((d) => ({ value: d.id, label: d.name })));
const onError = (errors) => toast.error(Object.values(errors)[0]);

// Departments
const deptOpen = ref(false);
const editingDept = ref(null);
const deptForm = useForm({ name: '', code: '', parent_id: null, manager_id: null, description: '', is_active: true });
const parentOptions = computed(() => departmentOptions.value.filter((d) => d.value !== editingDept.value?.id));
const openDept = (d = null) => {
    editingDept.value = d;
    deptForm.clearErrors();
    deptForm.defaults({ name: d?.name ?? '', code: d?.code ?? '', parent_id: d?.parent_id ?? null, manager_id: d?.manager_id ?? null, description: d?.description ?? '', is_active: d?.is_active ?? true }).reset();
    deptOpen.value = true;
};
const submitDept = () => {
    const options = { preserveScroll: true, onSuccess: () => { deptOpen.value = false; } };
    if (editingDept.value) deptForm.put(route('organisation.departments.update', editingDept.value.id), options);
    else deptForm.post(route('organisation.departments.store'), options);
};

// Positions
const posOpen = ref(false);
const editingPos = ref(null);
const posForm = useForm({ name: '', code: '', department_id: null, salary_min: null, salary_max: null, description: '', is_active: true });
const openPos = (p = null) => {
    editingPos.value = p;
    posForm.clearErrors();
    posForm.defaults({
        name: p?.name ?? '', code: p?.code ?? '', department_id: p?.department_id ?? props.departments[0]?.id ?? null,
        salary_min: p?.salary_min ? Number(p.salary_min) : null, salary_max: p?.salary_max ? Number(p.salary_max) : null,
        description: p?.description ?? '', is_active: p?.is_active ?? true,
    }).reset();
    posOpen.value = true;
};
const submitPos = () => {
    const options = { preserveScroll: true, onSuccess: () => { posOpen.value = false; } };
    if (editingPos.value) posForm.put(route('organisation.positions.update', editingPos.value.id), options);
    else posForm.post(route('organisation.positions.store'), options);
};
</script>

<template>
    <AppLayout title="Départements" :breadcrumbs="[{ title: 'Organisation' }, { title: 'Départements' }]">
        <PageHeader title="Départements et postes" description="Structure de la société : départements et postes rattachés." />

        <Card class="gap-4 pb-2">
            <CardHeader>
                <CardTitle>Départements</CardTitle>
                <CardAction v-if="can('departments.create')"><Button size="sm" @click="openDept()"><Plus /> Nouveau département</Button></CardAction>
            </CardHeader>
            <Table v-if="departments.length">
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Département</TableHead>
                        <TableHead class="hidden md:table-cell">Responsable</TableHead>
                        <TableHead class="text-right">Effectif</TableHead>
                        <TableHead class="pr-4"><span class="sr-only">Actions</span></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="d in departments" :key="d.id" :class="d.is_active ? '' : 'opacity-60'">
                        <TableCell class="pl-4">
                            <p class="font-medium">{{ d.name }} <Badge v-if="!d.is_active" variant="outline" class="ml-1">Inactif</Badge></p>
                            <p class="text-xs text-muted-foreground">{{ [d.code, d.parent ? `Rattaché à ${d.parent}` : null].filter(Boolean).join(' · ') }}</p>
                        </TableCell>
                        <TableCell class="hidden text-muted-foreground md:table-cell">{{ d.manager ?? '—' }}</TableCell>
                        <TableCell class="text-right tabular-nums">{{ d.personnels_count }}</TableCell>
                        <TableCell class="pr-4 text-right whitespace-nowrap">
                            <Button v-if="can('departments.update')" variant="ghost" size="icon" :aria-label="`Modifier ${d.name}`" @click="openDept(d)"><Pencil /></Button>
                            <Button v-if="can('departments.delete')" variant="ghost" size="icon" :aria-label="`Supprimer ${d.name}`"
                                @click="router.delete(route('organisation.departments.destroy', d.id), { preserveScroll: true, onError })"><Trash2 /></Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
            <Empty v-else>
                <EmptyHeader>
                    <EmptyMedia variant="icon"><Network /></EmptyMedia>
                    <EmptyTitle>Aucun département</EmptyTitle>
                    <EmptyDescription>Créez les départements (Commercial, Comptabilité…) avant les postes.</EmptyDescription>
                </EmptyHeader>
            </Empty>
        </Card>

        <Card class="gap-4 pb-2">
            <CardHeader>
                <CardTitle>Postes</CardTitle>
                <CardAction v-if="can('positions.create') && departments.length"><Button size="sm" variant="outline" @click="openPos()"><Plus /> Nouveau poste</Button></CardAction>
            </CardHeader>
            <Table v-if="positions.length">
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Poste</TableHead>
                        <TableHead class="hidden sm:table-cell">Département</TableHead>
                        <TableHead class="hidden text-right md:table-cell">Fourchette salariale</TableHead>
                        <TableHead class="text-right">Effectif</TableHead>
                        <TableHead class="pr-4"><span class="sr-only">Actions</span></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="p in positions" :key="p.id" :class="p.is_active ? '' : 'opacity-60'">
                        <TableCell class="pl-4"><p class="font-medium">{{ p.name }}</p><p v-if="p.code" class="text-xs text-muted-foreground">{{ p.code }}</p></TableCell>
                        <TableCell class="hidden text-muted-foreground sm:table-cell">{{ p.department ?? '—' }}</TableCell>
                        <TableCell class="hidden text-right tabular-nums text-muted-foreground md:table-cell">
                            <template v-if="p.salary_min || p.salary_max">{{ formatAmount(p.salary_min) }} – {{ formatAmount(p.salary_max) }}</template><template v-else>—</template>
                        </TableCell>
                        <TableCell class="text-right tabular-nums">{{ p.personnels_count }}</TableCell>
                        <TableCell class="pr-4 text-right whitespace-nowrap">
                            <Button v-if="can('positions.update')" variant="ghost" size="icon" :aria-label="`Modifier ${p.name}`" @click="openPos(p)"><Pencil /></Button>
                            <Button v-if="can('positions.delete')" variant="ghost" size="icon" :aria-label="`Supprimer ${p.name}`"
                                @click="router.delete(route('organisation.positions.destroy', p.id), { preserveScroll: true, onError })"><Trash2 /></Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
            <p v-else class="px-6 pb-4 text-sm text-muted-foreground">Aucun poste.</p>
        </Card>

        <Dialog v-model:open="deptOpen">
            <DialogContent>
                <DialogHeader><DialogTitle>{{ editingDept ? `Modifier ${editingDept.name}` : 'Nouveau département' }}</DialogTitle></DialogHeader>
                <form class="grid gap-4" @submit.prevent="submitDept">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <FormField id="dept-name" v-model="deptForm.name" label="Nom" required class="sm:col-span-2" :error="deptForm.errors.name" />
                        <FormField id="dept-code" v-model="deptForm.code" label="Code" :error="deptForm.errors.code" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField id="dept-parent" v-model="deptForm.parent_id" label="Rattaché à" :options="parentOptions" placeholder="Aucun" :error="deptForm.errors.parent_id" />
                        <FormField id="dept-manager" v-model="deptForm.manager_id" label="Responsable" :options="managers" placeholder="Aucun" :error="deptForm.errors.manager_id" />
                    </div>
                    <FormField id="dept-description" v-model="deptForm.description" label="Description" type="textarea" :error="deptForm.errors.description" />
                    <div class="flex items-center gap-2"><Checkbox id="dept-active" v-model="deptForm.is_active" /><Label for="dept-active" class="font-normal">Département actif</Label></div>
                    <DialogFooter><Button type="submit" :disabled="deptForm.processing">{{ editingDept ? 'Enregistrer' : 'Créer' }}</Button></DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="posOpen">
            <DialogContent>
                <DialogHeader><DialogTitle>{{ editingPos ? `Modifier ${editingPos.name}` : 'Nouveau poste' }}</DialogTitle></DialogHeader>
                <form class="grid gap-4" @submit.prevent="submitPos">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <FormField id="pos-name" v-model="posForm.name" label="Intitulé" required class="sm:col-span-2" :error="posForm.errors.name" />
                        <FormField id="pos-code" v-model="posForm.code" label="Code" :error="posForm.errors.code" />
                    </div>
                    <FormField id="pos-department" v-model="posForm.department_id" label="Département" :options="departmentOptions" required :error="posForm.errors.department_id" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField id="pos-min" v-model="posForm.salary_min" label="Salaire minimum" type="number" :error="posForm.errors.salary_min" />
                        <FormField id="pos-max" v-model="posForm.salary_max" label="Salaire maximum" type="number" :error="posForm.errors.salary_max" />
                    </div>
                    <div class="flex items-center gap-2"><Checkbox id="pos-active" v-model="posForm.is_active" /><Label for="pos-active" class="font-normal">Poste actif</Label></div>
                    <DialogFooter><Button type="submit" :disabled="posForm.processing">{{ editingPos ? 'Enregistrer' : 'Créer' }}</Button></DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
