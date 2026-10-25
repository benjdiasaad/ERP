<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Tags, Trash2 } from '@lucide/vue';
import { toast } from 'vue-sonner';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import FormField from '@/components/FormField.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { usePermissions } from '@/composables/usePermissions';

const props = defineProps({
    categories: { type: Object, required: true },
});

const { can } = usePermissions();
const open = ref(false);
const editing = ref(null);
const form = useForm({ name: '', code: '', parent_id: null, description: '', is_active: true });

// A category cannot be its own parent.
const parentOptions = computed(() => props.categories.data
    .filter((c) => c.id !== editing.value?.id)
    .map((c) => ({ value: c.id, label: c.name })));

const openForm = (category = null) => {
    editing.value = category;
    form.clearErrors();
    form.defaults({
        name: category?.name ?? '', code: category?.code ?? '', parent_id: category?.parent_id ?? null,
        description: category?.description ?? '', is_active: category?.is_active ?? true,
    }).reset();
    open.value = true;
};

const submit = () => {
    const options = { preserveScroll: true, onSuccess: () => { open.value = false; } };
    if (editing.value) form.put(route('stock.categories.update', editing.value.id), options);
    else form.post(route('stock.categories.store'), options);
};

const destroy = (category) => router.delete(route('stock.categories.destroy', category.id), {
    preserveScroll: true,
    onError: (errors) => toast.error(Object.values(errors)[0]),
});
</script>

<template>
    <AppLayout title="Catégories" :breadcrumbs="[{ title: 'Stock' }, { title: 'Catégories' }]">
        <PageHeader title="Catégories de produits" description="Classement des produits du catalogue.">
            <template #actions>
                <Button v-if="can('product_categories.create')" @click="openForm()"><Plus /> Nouvelle catégorie</Button>
            </template>
        </PageHeader>

        <Card class="py-2">
            <Table v-if="categories.data.length">
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Catégorie</TableHead>
                        <TableHead class="hidden sm:table-cell">Parente</TableHead>
                        <TableHead class="text-right">Produits</TableHead>
                        <TableHead>Statut</TableHead>
                        <TableHead class="pr-4"><span class="sr-only">Actions</span></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="category in categories.data" :key="category.id">
                        <TableCell class="pl-4">
                            <p class="font-medium">{{ category.name }}</p>
                            <p v-if="category.code || category.description" class="max-w-md truncate text-xs text-muted-foreground">
                                {{ [category.code, category.description].filter(Boolean).join(' · ') }}
                            </p>
                        </TableCell>
                        <TableCell class="hidden text-muted-foreground sm:table-cell">{{ category.parent?.name ?? '—' }}</TableCell>
                        <TableCell class="text-right tabular-nums">
                            <Link v-if="category.products_count" :href="route('stock.products.index', { category_id: category.id })" class="hover:underline">{{ category.products_count }}</Link>
                            <span v-else class="text-muted-foreground">0</span>
                        </TableCell>
                        <TableCell><Badge :variant="category.is_active ? 'secondary' : 'outline'">{{ category.is_active ? 'Active' : 'Inactive' }}</Badge></TableCell>
                        <TableCell class="pr-4 text-right whitespace-nowrap">
                            <Button v-if="can('product_categories.update')" variant="ghost" size="icon" :aria-label="`Modifier ${category.name}`" @click="openForm(category)"><Pencil /></Button>
                            <Button v-if="can('product_categories.delete')" variant="ghost" size="icon" :aria-label="`Supprimer ${category.name}`" @click="destroy(category)"><Trash2 /></Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <Empty v-else>
                <EmptyHeader>
                    <EmptyMedia variant="icon"><Tags /></EmptyMedia>
                    <EmptyTitle>Aucune catégorie</EmptyTitle>
                    <EmptyDescription>Créez des catégories pour classer vos produits.</EmptyDescription>
                </EmptyHeader>
            </Empty>
        </Card>

        <Dialog v-model:open="open">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ editing ? `Modifier ${editing.name}` : 'Nouvelle catégorie' }}</DialogTitle>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <FormField id="category-name" v-model="form.name" label="Nom" required class="sm:col-span-2" :error="form.errors.name" />
                        <FormField id="category-code" v-model="form.code" label="Code" :error="form.errors.code" />
                    </div>
                    <FormField id="category-parent" v-model="form.parent_id" label="Catégorie parente" :options="parentOptions" placeholder="Aucune" :error="form.errors.parent_id" />
                    <FormField id="category-description" v-model="form.description" label="Description" type="textarea" :error="form.errors.description" />
                    <div class="flex items-center gap-2">
                        <Checkbox id="category-active" v-model="form.is_active" />
                        <Label for="category-active" class="font-normal">Catégorie active</Label>
                    </div>
                    <DialogFooter>
                        <Button type="submit" :disabled="form.processing">{{ editing ? 'Enregistrer' : 'Créer' }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
