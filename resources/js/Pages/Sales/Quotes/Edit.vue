<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import QuoteForm from './Partials/QuoteForm.vue';

const props = defineProps({
    quote: { type: Object, required: true },
    customers: { type: Array, required: true },
    products: { type: Array, required: true },
    taxes: { type: Array, required: true },
    paymentTerms: { type: Array, required: true },
    currencies: { type: Array, required: true },
});

const q = props.quote.data;
const form = useForm({
    customer_id: q.customer_id,
    date: q.quote_date,
    validity_date: q.validity_date,
    payment_term_id: q.payment_term_id,
    currency_id: q.currency_id,
    notes: q.notes,
    lines: q.lines.map((l) => ({
        product_id: l.product_id,
        description: l.description,
        quantity: Number(l.quantity),
        unit_price_ht: Number(l.unit_price_ht),
        discount_type: l.discount_type ?? 'percentage',
        discount_value: Number(l.discount_value ?? 0),
        tax_rate: Number(l.tax_rate ?? 0),
    })),
});

const submit = () => form.put(route('sales.quotes.update', q.id));
</script>

<template>
    <AppLayout :title="`Modifier ${q.reference}`" :breadcrumbs="[
        { title: 'Ventes' }, { title: 'Devis', href: route('sales.quotes.index') },
        { title: q.reference, href: route('sales.quotes.show', q.id) }, { title: 'Modifier' },
    ]">
        <PageHeader :title="`Modifier ${q.reference}`" />
        <QuoteForm :form="form" :customers="customers" :products="products" :taxes="taxes" :payment-terms="paymentTerms" :currencies="currencies"
            submit-label="Enregistrer" :cancel-href="route('sales.quotes.show', q.id)" @submit="submit" />
    </AppLayout>
</template>
