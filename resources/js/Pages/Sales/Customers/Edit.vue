<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import CustomerForm from './Partials/CustomerForm.vue';

const props = defineProps({
    customer: { type: Object, required: true },
    paymentTerms: { type: Array, required: true },
    currencies: { type: Array, required: true },
});

const c = props.customer.data;
const form = useForm({
    type: c.type, name: c.name, first_name: c.first_name, last_name: c.last_name, ice: c.ice, rc: c.rc, tax_id: c.tax_id,
    email: c.email, phone: c.phone, mobile: c.mobile, address: c.address, postal_code: c.postal_code, city: c.city, country: c.country,
    payment_terms_id: c.payment_terms_id, currency_id: c.currency_id, credit_limit: c.credit_limit, notes: c.notes, is_active: c.is_active,
});

// For individuals the display name is rebuilt from first/last name on save.
const submit = () => form.transform((data) => (data.type === 'individual' ? { ...data, name: null } : data))
    .put(route('sales.customers.update', c.id));
</script>

<template>
    <AppLayout :title="`Modifier ${c.name}`" :breadcrumbs="[
        { title: 'Ventes' }, { title: 'Clients', href: route('sales.customers.index') },
        { title: c.name, href: route('sales.customers.show', c.id) }, { title: 'Modifier' },
    ]">
        <PageHeader :title="`Modifier ${c.name}`" :description="c.code" />
        <CustomerForm :form="form" :payment-terms="paymentTerms" :currencies="currencies"
            submit-label="Enregistrer" :cancel-href="route('sales.customers.show', c.id)" @submit="submit" />
    </AppLayout>
</template>
