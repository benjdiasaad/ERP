<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Tag, Trash2 } from '@lucide/vue';
import { toast } from 'vue-sonner';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { usePermissions } from '@/composables/usePermissions';

defineProps({
    categories: { type: Object, required: true },
});

const { can } = usePermissions();
const open = ref(false);
const editing = ref(null);
const form = useForm({ name: '', color: '#2563eb', description: '' });

const openForm = (category = null) => {
    editing.value = category;
    form.clearErrors();
    form.defaults({ name: category?.name ?? '', color: category?.color ?? '#2563eb', description: category?.description ?? '' }).reset();
    open.value = true;
};
const submit = () => {
    const options = { preserveScroll: true, onSuccess: () => { open.value = false; } };
    if (editing.value) form.put(route('organisation.event-categories.update', editing.value.id), options);
    else form.post(route('organisation.event-categories.store'), options);
};
const destroy = (category) => router.delete(route('organisation.event-categories.destroy', category.id), {
    preserveScroll: true, onError: (errors) => toast.error(Object.values(errors)[0]),
});
</script>

<template>
    <AppLayout title="Catégories d'événements" :breadcrumbs="[{ title: 'Organisation' }, { title: `Catégories d'événements` }]">
        <PageHeader title="Catégories d'événements" description="Classement et couleur des événements.">
            <template #actions>
                <Button v-if="can('event_categories.create')" @click="openForm()"><Plus /> Nouvelle catégorie</Button>
            </template>
        </PageHeader>

        <Card class="py-2">
            <Table v-if="categories.data.length">
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Catégorie</TableHead>
                        <TableHead class="text-right">Événements</TableHead>
                        <TableHead class="pr-4"><span class="sr-only">Actions</span></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="c in categories.data" :key="c.id">
                        <TableCell class="pl-4">
                            <div class="flex items-center gap-2">
                                <span class="size-3 rounded-full bg-muted-foreground" :style="c.color ? { backgroundColor: c.color } : null" aria-hidden="true" />
                                <span class="font-medium">{{ c.name }}</span>
                            </div>
                            <p v-if="c.description" class="max-w-md truncate pl-5 text-xs text-muted-foreground">{{ c.description }}</p>
                        </TableCell>
                        <TableCell class="text-right tabular-nums">{{ c.events_count ?? 0 }}</TableCell>
                        <TableCell class="pr-4 text-right whitespace-nowrap">
                            <Button v-if="can('event_categories.update')" variant="ghost" size="icon" :aria-label="`Modifier ${c.name}`" @click="openForm(c)"><Pencil /></Button>
                            <Button v-if="can('event_categories.delete')" variant="ghost" size="icon" :aria-label="`Supprimer ${c.name}`" @click="destroy(c)"><Trash2 /></Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
            <Empty v-else>
                <EmptyHeader>
                    <EmptyMedia variant="icon"><Tag /></EmptyMedia>
                    <EmptyTitle>Aucune catégorie</EmptyTitle>
                    <EmptyDescription>Créez une catégorie avant votre premier événement.</EmptyDescription>
                </EmptyHeader>
            </Empty>
        </Card>

        <Dialog v-model:open="open">
            <DialogContent>
                <DialogHeader><DialogTitle>{{ editing ? `Modifier ${editing.name}` : 'Nouvelle catégorie' }}</DialogTitle></DialogHeader>
                <form class="grid gap-4" @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-4">
                        <FormField id="category-name" v-model="form.name" label="Nom" required class="sm:col-span-3" :error="form.errors.name" />
                        <div class="grid gap-2">
                            <Label for="category-color">Couleur</Label>
                            <Input id="category-color" v-model="form.color" type="color" class="h-9 p-1" />
                        </div>
                    </div>
                    <FormField id="category-description" v-model="form.description" label="Description" type="textarea" :error="form.errors.description" />
                    <DialogFooter><Button type="submit" :disabled="form.processing">{{ editing ? 'Enregistrer' : 'Créer' }}</Button></DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
