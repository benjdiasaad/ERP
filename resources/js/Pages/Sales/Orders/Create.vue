<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import OrderForm from './Partials/OrderForm.vue';

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
    order_date: props.defaults.order_date,
    expected_delivery_date: null,
    delivery_address: customer?.address ?? '',
    payment_term_id: customer?.payment_term_id ?? null,
    currency_id: props.defaults.currency_id,
    notes: '',
    lines: [{ product_id: null, description: '', quantity: 1, unit_price_ht: 0, discount_type: 'percentage', discount_value: 0, tax_rate: defaultRate }],
});

const submit = () => form.post(route('sales.orders.store'));
</script>

<template>
    <AppLayout title="Nouvelle commande" :breadcrumbs="[{ title: 'Ventes' }, { title: 'Commandes', href: route('sales.orders.index') }, { title: 'Nouvelle' }]">
        <PageHeader title="Nouvelle commande" description="Créée en brouillon ; confirmez-la pour lancer livraison et facturation." />
        <OrderForm :form="form" :customers="customers" :products="products" :taxes="taxes" :payment-terms="paymentTerms" :currencies="currencies"
            submit-label="Créer la commande" :cancel-href="route('sales.orders.index')" @submit="submit" />
    </AppLayout>
</template>
