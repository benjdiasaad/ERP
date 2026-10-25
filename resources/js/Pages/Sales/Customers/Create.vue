<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import CustomerForm from './Partials/CustomerForm.vue';

defineProps({
    paymentTerms: { type: Array, required: true },
    currencies: { type: Array, required: true },
});

const form = useForm({
    type: 'company', name: '', first_name: '', last_name: '', ice: '', rc: '', tax_id: '',
    email: '', phone: '', mobile: '', address: '', postal_code: '', city: '', country: 'MA',
    payment_terms_id: null, currency_id: null, credit_limit: null, notes: '', is_active: true,
});

// Only send the identity fields of the chosen type.
const submit = () => form.transform((data) => (data.type === 'company'
    ? { ...data, first_name: null, last_name: null }
    : { ...data, name: null, ice: null, rc: null, tax_id: null }))
    .post(route('sales.customers.store'));
</script>

<template>
    <AppLayout title="Nouveau client" :breadcrumbs="[{ title: 'Ventes' }, { title: 'Clients', href: route('sales.customers.index') }, { title: 'Nouveau' }]">
        <PageHeader title="Nouveau client" />
        <CustomerForm :form="form" :payment-terms="paymentTerms" :currencies="currencies"
            submit-label="Créer le client" :cancel-href="route('sales.customers.index')" @submit="submit" />
    </AppLayout>
</template>
