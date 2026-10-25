<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

const props = defineProps({
    warehouses: { type: Array, required: true },
});

const requested = Number(new URLSearchParams(window.location.search).get('warehouse_id'));
const form = useForm({
    warehouse_id: props.warehouses.find((w) => w.value === requested)?.value ?? props.warehouses[0]?.value ?? null,
    count_date: new Date().toISOString().slice(0, 10),
    notes: '',
    all_products: true,
});

const submit = () => form.post(route('stock.inventories.store'));
</script>

<template>
    <AppLayout title="Nouvel inventaire" :breadcrumbs="[{ title: 'Stock' }, { title: 'Inventaires', href: route('stock.inventories.index') }, { title: 'Nouveau' }]">
        <PageHeader title="Nouvel inventaire" />
        <form class="grid gap-6" @submit.prevent="submit">
            <Card>
                <CardHeader>
                    <CardTitle>Feuille de comptage</CardTitle>
                    <CardDescription>Vous pourrez ajouter ou retirer des produits avant de démarrer le comptage.</CardDescription>
                </CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-2">
                    <FormField id="warehouse_id" v-model="form.warehouse_id" label="Entrepôt" :options="warehouses" required :error="form.errors.warehouse_id" />
                    <FormField id="count_date" v-model="form.count_date" label="Date du comptage" type="date" :error="form.errors.count_date" />
                    <FormField id="notes" v-model="form.notes" label="Notes" type="textarea" class="sm:col-span-2" :error="form.errors.notes" />
                    <div class="flex items-center gap-2 sm:col-span-2">
                        <Checkbox id="all_products" v-model="form.all_products" />
                        <Label for="all_products" class="font-normal">Inclure tous les produits stockés actifs</Label>
                    </div>
                </CardContent>
            </Card>
            <div class="flex justify-end gap-2">
                <Button variant="outline" as-child><Link :href="route('stock.inventories.index')">Annuler</Link></Button>
                <Button type="submit" :disabled="form.processing">Créer l'inventaire</Button>
            </div>
        </form>
    </AppLayout>
</template>
