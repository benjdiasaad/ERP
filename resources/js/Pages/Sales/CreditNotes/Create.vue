<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import CreditNoteForm from './Partials/CreditNoteForm.vue';

const props = defineProps({
    customers: { type: Array, required: true },
    invoices: { type: Array, required: true },
    products: { type: Array, required: true },
    taxes: { type: Array, required: true },
    defaults: { type: Object, required: true },
});

const defaultRate = props.taxes.length ? Math.max(...props.taxes.map((t) => t.rate)) : 20;
const form = useForm({
    customer_id: props.defaults.customer_id,
    invoice_id: null,
    date: props.defaults.date,
    reason: '',
    notes: '',
    lines: [{ product_id: null, description: '', quantity: 1, unit_price_ht: 0, discount_type: 'percentage', discount_value: 0, tax_rate: defaultRate }],
});

const submit = () => form.post(route('sales.credit-notes.store'));
</script>

<template>
    <AppLayout title="Nouvel avoir" :breadcrumbs="[{ title: 'Ventes' }, { title: 'Avoirs', href: route('sales.credit-notes.index') }, { title: 'Nouveau' }]">
        <PageHeader title="Nouvel avoir" />
        <CreditNoteForm :form="form" :customers="customers" :invoices="invoices" :products="products" :taxes="taxes"
            submit-label="Créer l'avoir" :cancel-href="route('sales.credit-notes.index')" @submit="submit" />
    </AppLayout>
</template>
