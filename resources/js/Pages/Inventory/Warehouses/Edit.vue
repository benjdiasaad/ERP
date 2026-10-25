<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import WarehouseForm from './Partials/WarehouseForm.vue';

const props = defineProps({
    warehouse: { type: Object, required: true },
    managers: { type: Array, required: true },
});

const w = props.warehouse.data;
const form = useForm({
    name: w.name, code: w.code, manager_id: w.manager_id, is_default: w.is_default, is_active: w.is_active,
    address: w.address, postal_code: w.postal_code, city: w.city, country: w.country, notes: w.notes,
});

const submit = () => form.put(route('stock.warehouses.update', w.id));
</script>

<template>
    <AppLayout :title="`Modifier ${w.name}`" :breadcrumbs="[
        { title: 'Stock' }, { title: 'Entrepôts', href: route('stock.warehouses.index') },
        { title: w.name, href: route('stock.warehouses.show', w.id) }, { title: 'Modifier' },
    ]">
        <PageHeader :title="`Modifier ${w.name}`" :description="w.code" />
        <WarehouseForm :form="form" :managers="managers" submit-label="Enregistrer"
            :cancel-href="route('stock.warehouses.show', w.id)" @submit="submit" />
    </AppLayout>
</template>
