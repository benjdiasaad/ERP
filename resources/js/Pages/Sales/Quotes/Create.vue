<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import QuoteForm from './Partials/QuoteForm.vue';

const props = defineProps({
    customers: { type: Array, required: true },
    products: { type: Array, required: true },
    taxes: { type: Array, required: true },
    paymentTerms: { type: Array, required: true },
    currencies: { type: Array, required: true },
    defaults: { type: Object, required: true },
});

const defaultRate = props.taxes.length ? Math.max(...props.taxes.map((t) => t.rate)) : 20;
const customer = props.customers.find((c) => c.value === props.defaults.customer_id);

const form = useForm({
    customer_id: props.defaults.customer_id,
    date: props.defaults.date,
    validity_date: props.defaults.validity_date,
    payment_term_id: customer?.payment_term_id ?? null,
    currency_id: props.defaults.currency_id,
    notes: '',
    lines: [{ product_id: null, description: '', quantity: 1, unit_price_ht: 0, discount_type: 'percentage', discount_value: 0, tax_rate: defaultRate }],
});

const submit = () => form.post(route('sales.quotes.store'));
</script>

<template>
    <AppLayout title="Nouveau devis" :breadcrumbs="[{ title: 'Ventes' }, { title: 'Devis', href: route('sales.quotes.index') }, { title: 'Nouveau' }]">
        <PageHeader title="Nouveau devis" description="Le devis est créé en brouillon ; vous pourrez le modifier avant de l'envoyer." />
        <QuoteForm :form="form" :customers="customers" :products="products" :taxes="taxes" :payment-terms="paymentTerms" :currencies="currencies"
            submit-label="Créer le devis" :cancel-href="route('sales.quotes.index')" @submit="submit" />
    </AppLayout>
</template>
