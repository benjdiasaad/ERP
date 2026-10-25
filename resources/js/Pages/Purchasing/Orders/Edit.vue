<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import OrderForm from './Partials/OrderForm.vue';

const props = defineProps({
    order: { type: Object, required: true },
    suppliers: { type: Array, required: true },
    products: { type: Array, required: true },
    taxes: { type: Array, required: true },
    paymentTerms: { type: Array, required: true },
    currencies: { type: Array, required: true },
});

const o = props.order.data;
const form = useForm({
    supplier_id: o.supplier_id,
    order_date: o.order_date,
    expected_delivery_date: o.expected_delivery_date,
    delivery_address: o.delivery_address,
    payment_term_id: o.payment_term_id,
    currency_id: o.currency_id,
    notes: o.notes,
    lines: o.lines.map((l) => ({
        product_id: l.product_id,
        description: l.description,
        quantity: Number(l.quantity),
        unit_price_ht: Number(l.unit_price_ht),
        discount_type: l.discount_type ?? 'percentage',
        discount_value: Number(l.discount_value ?? 0),
        tax_rate: Number(l.tax_rate ?? 0),
    })),
});

const submit = () => form.put(route('purchasing.orders.update', o.id));
</script>

<template>
    <AppLayout :title="`Modifier ${o.reference}`" :breadcrumbs="[
        { title: 'Achats' }, { title: 'Bons de commande', href: route('purchasing.orders.index') },
        { title: o.reference, href: route('purchasing.orders.show', o.id) }, { title: 'Modifier' },
    ]">
        <PageHeader :title="`Modifier ${o.reference}`" />
        <OrderForm :form="form" :suppliers="suppliers" :products="products" :taxes="taxes" :payment-terms="paymentTerms" :currencies="currencies"
            submit-label="Enregistrer" :cancel-href="route('purchasing.orders.show', o.id)" @submit="submit" />
    </AppLayout>
</template>
