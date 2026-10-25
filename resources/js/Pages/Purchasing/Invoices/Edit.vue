<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import InvoiceForm from './Partials/InvoiceForm.vue';

const props = defineProps({
    invoice: { type: Object, required: true },
    suppliers: { type: Array, required: true },
    products: { type: Array, required: true },
    taxes: { type: Array, required: true },
    paymentTerms: { type: Array, required: true },
    currencies: { type: Array, required: true },
});

const i = props.invoice.data;
const form = useForm({
    supplier_id: i.supplier_id,
    invoice_date: i.invoice_date,
    due_date: i.due_date,
    payment_term_id: i.payment_term_id,
    currency_id: i.currency_id,
    notes: i.notes,
    lines: i.lines.map((l) => ({
        product_id: l.product_id,
        description: l.description,
        quantity: Number(l.quantity),
        unit_price_ht: Number(l.unit_price_ht),
        discount_type: l.discount_type ?? 'percentage',
        discount_value: Number(l.discount_value ?? 0),
        tax_rate: Number(l.tax_rate ?? 0),
    })),
});

const submit = () => form.put(route('purchasing.invoices.update', i.id));
</script>

<template>
    <AppLayout :title="`Modifier ${i.reference}`" :breadcrumbs="[
        { title: 'Achats' }, { title: 'Factures fournisseurs', href: route('purchasing.invoices.index') },
        { title: i.reference, href: route('purchasing.invoices.show', i.id) }, { title: 'Modifier' },
    ]">
        <PageHeader :title="`Modifier ${i.reference}`" />
        <InvoiceForm :form="form" :suppliers="suppliers" :products="products" :taxes="taxes" :payment-terms="paymentTerms" :currencies="currencies"
            submit-label="Enregistrer" :cancel-href="route('purchasing.invoices.show', i.id)" @submit="submit" />
    </AppLayout>
</template>
