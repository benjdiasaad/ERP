<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import WarehouseForm from './Partials/WarehouseForm.vue';

defineProps({
    managers: { type: Array, required: true },
});

const form = useForm({
    name: '', code: '', manager_id: null, is_default: false, is_active: true,
    address: '', postal_code: '', city: '', country: 'MA', notes: '',
});

const submit = () => form.post(route('stock.warehouses.store'));
</script>

<template>
    <AppLayout title="Nouvel entrepôt" :breadcrumbs="[{ title: 'Stock' }, { title: 'Entrepôts', href: route('stock.warehouses.index') }, { title: 'Nouveau' }]">
        <PageHeader title="Nouvel entrepôt" />
        <WarehouseForm :form="form" :managers="managers" submit-label="Créer l'entrepôt"
            :cancel-href="route('stock.warehouses.index')" @submit="submit" />
    </AppLayout>
</template>
