<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import InvoiceForm from './Partials/InvoiceForm.vue';

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
    invoice_date: props.defaults.invoice_date,
    due_date: props.defaults.invoice_date,
    payment_term_id: supplier?.payment_term_id ?? null,
    currency_id: props.defaults.currency_id,
    notes: '',
    lines: [{ product_id: null, description: '', quantity: 1, unit_price_ht: 0, discount_type: 'percentage', discount_value: 0, tax_rate: defaultRate }],
});

const submit = () => form.post(route('purchasing.invoices.store'));
</script>

<template>
    <AppLayout title="Nouvelle facture fournisseur" :breadcrumbs="[{ title: 'Achats' }, { title: 'Factures fournisseurs', href: route('purchasing.invoices.index') }, { title: 'Nouvelle' }]">
        <PageHeader title="Nouvelle facture fournisseur" description="Pour une commande, utilisez plutôt « Facture fournisseur » depuis le bon de commande." />
        <InvoiceForm :form="form" :suppliers="suppliers" :products="products" :taxes="taxes" :payment-terms="paymentTerms" :currencies="currencies"
            submit-label="Enregistrer la facture" :cancel-href="route('purchasing.invoices.index')" @submit="submit" />
    </AppLayout>
</template>
