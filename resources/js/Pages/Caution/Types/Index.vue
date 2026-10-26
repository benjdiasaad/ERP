<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Shapes, Trash2 } from '@lucide/vue';
import { toast } from 'vue-sonner';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import FormField from '@/components/FormField.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { usePermissions } from '@/composables/usePermissions';
import { formatPercent } from '@/composables/format';

defineProps({
    types: { type: Object, required: true },
});

const { can } = usePermissions();
const open = ref(false);
const editing = ref(null);
const form = useForm({ name: '', description: '', default_percentage: 0, is_active: true });

const openForm = (type = null) => {
    editing.value = type;
    form.clearErrors();
    form.defaults({
        name: type?.name ?? '', description: type?.description ?? '',
        default_percentage: Number(type?.default_percentage ?? 0), is_active: type?.is_active ?? true,
    }).reset();
    open.value = true;
};

const submit = () => {
    const options = { preserveScroll: true, onSuccess: () => { open.value = false; } };
    if (editing.value) form.put(route('finance.caution-types.update', editing.value.id), options);
    else form.post(route('finance.caution-types.store'), options);
};

const destroy = (type) => router.delete(route('finance.caution-types.destroy', type.id), {
    preserveScroll: true,
    onError: (errors) => toast.error(Object.values(errors)[0]),
});
</script>

<template>
    <AppLayout title="Types de caution" :breadcrumbs="[{ title: 'Finance' }, { title: 'Types de caution' }]">
        <PageHeader title="Types de caution" description="Caution provisoire, définitive, de retenue de garantie…">
            <template #actions>
                <Button v-if="can('caution_types.create')" @click="openForm()"><Plus /> Nouveau type</Button>
            </template>
        </PageHeader>

        <Card class="py-2">
            <Table v-if="types.data.length">
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Type</TableHead>
                        <TableHead class="text-right">Taux par défaut</TableHead>
                        <TableHead class="hidden text-right sm:table-cell">Cautions</TableHead>
                        <TableHead>Statut</TableHead>
                        <TableHead class="pr-4"><span class="sr-only">Actions</span></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="type in types.data" :key="type.id">
                        <TableCell class="pl-4">
                            <p class="font-medium">{{ type.name }}</p>
                            <p v-if="type.description" class="max-w-md truncate text-xs text-muted-foreground">{{ type.description }}</p>
                        </TableCell>
                        <TableCell class="text-right tabular-nums">{{ formatPercent(type.default_percentage) }}</TableCell>
                        <TableCell class="hidden text-right tabular-nums sm:table-cell">{{ type.cautions_count ?? 0 }}</TableCell>
                        <TableCell><Badge :variant="type.is_active ? 'secondary' : 'outline'">{{ type.is_active ? 'Actif' : 'Inactif' }}</Badge></TableCell>
                        <TableCell class="pr-4 text-right whitespace-nowrap">
                            <Button v-if="can('caution_types.update')" variant="ghost" size="icon" :aria-label="`Modifier ${type.name}`" @click="openForm(type)"><Pencil /></Button>
                            <Button v-if="can('caution_types.delete')" variant="ghost" size="icon" :aria-label="`Supprimer ${type.name}`" @click="destroy(type)"><Trash2 /></Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <Empty v-else>
                <EmptyHeader>
                    <EmptyMedia variant="icon"><Shapes /></EmptyMedia>
                    <EmptyTitle>Aucun type de caution</EmptyTitle>
                    <EmptyDescription>Créez au moins un type avant d'enregistrer une caution.</EmptyDescription>
                </EmptyHeader>
            </Empty>
        </Card>

        <Dialog v-model:open="open">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ editing ? `Modifier ${editing.name}` : 'Nouveau type de caution' }}</DialogTitle>
                    <DialogDescription>Le taux par défaut s'applique au montant du marché pour proposer le montant de la caution.</DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <FormField id="type-name" v-model="form.name" label="Nom" required class="sm:col-span-2" :error="form.errors.name" />
                        <FormField id="type-percentage" v-model="form.default_percentage" label="Taux (%)" type="number" required :error="form.errors.default_percentage" />
                    </div>
                    <FormField id="type-description" v-model="form.description" label="Description" type="textarea" :error="form.errors.description" />
                    <div class="flex items-center gap-2">
                        <Checkbox id="type-active" v-model="form.is_active" />
                        <Label for="type-active" class="font-normal">Type actif</Label>
                    </div>
                    <DialogFooter>
                        <Button type="submit" :disabled="form.processing">{{ editing ? 'Enregistrer' : 'Créer' }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
