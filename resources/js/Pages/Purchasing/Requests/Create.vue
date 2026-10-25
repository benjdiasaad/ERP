<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import RequestForm from './Partials/RequestForm.vue';

defineProps({
    suppliers: { type: Array, required: true },
    products: { type: Array, required: true },
});

const form = useForm({
    title: '', priority: 'medium', required_date: null, supplier_id: null, description: '',
    lines: [{ description: '', quantity: 1, unit: '', estimated_unit_price: 0 }],
});

const submit = () => form.post(route('purchasing.requests.store'));
</script>

<template>
    <AppLayout title="Nouvelle demande d'achat" :breadcrumbs="[{ title: 'Achats' }, { title: 'Demandes d\'achat', href: route('purchasing.requests.index') }, { title: 'Nouvelle' }]">
        <PageHeader title="Nouvelle demande d'achat" description="Créée en brouillon ; soumettez-la pour approbation." />
        <RequestForm :form="form" :suppliers="suppliers" submit-label="Créer la demande" :cancel-href="route('purchasing.requests.index')" @submit="submit" />
    </AppLayout>
</template>
