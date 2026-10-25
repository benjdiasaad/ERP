<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import OrderForm from './Partials/OrderForm.vue';

const props = defineProps({
    suppliers: { type: Array, required: true },
    products: { type: Array, required: true },
    taxes: { type: Array, required: true },
    paymentTerms: { type: Array, required: true },
    currencies: { type: Array, required: true },
    defaults: { type: Object, required: true },
});

const defaultRate = props.taxes.length ? Math.max(...props.taxes.map((t) => t.rate)) : 20;
const supplier = props.suppliers.find((s) => s.value === props.defaults.supplier_id);

const form = useForm({
    supplier_id: props.defaults.supplier_id,
    order_date: props.defaults.order_date,
    expected_delivery_date: null,
    delivery_address: '',
    payment_term_id: supplier?.payment_term_id ?? null,
    currency_id: props.defaults.currency_id,
    notes: '',
    lines: [{ product_id: null, description: '', quantity: 1, unit_price_ht: 0, discount_type: 'percentage', discount_value: 0, tax_rate: defaultRate }],
});

const submit = () => form.post(route('purchasing.orders.store'));
</script>

<template>
    <AppLayout title="Nouveau bon de commande" :breadcrumbs="[{ title: 'Achats' }, { title: 'Bons de commande', href: route('purchasing.orders.index') }, { title: 'Nouveau' }]">
        <PageHeader title="Nouveau bon de commande" description="Créé en brouillon ; envoyez-le au fournisseur une fois vérifié." />
        <OrderForm :form="form" :suppliers="suppliers" :products="products" :taxes="taxes" :payment-terms="paymentTerms" :currencies="currencies"
            submit-label="Créer le bon de commande" :cancel-href="route('purchasing.orders.index')" @submit="submit" />
    </AppLayout>
</template>
