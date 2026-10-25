<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import CreditNoteForm from './Partials/CreditNoteForm.vue';

const props = defineProps({
    note: { type: Object, required: true },
    customers: { type: Array, required: true },
    invoices: { type: Array, required: true },
    products: { type: Array, required: true },
    taxes: { type: Array, required: true },
});

const n = props.note.data;
const form = useForm({
    customer_id: n.customer_id,
    invoice_id: n.invoice_id,
    date: n.date,
    reason: n.reason,
    notes: n.notes,
    lines: n.lines.map((l) => ({
        product_id: l.product_id,
        description: l.description,
        quantity: Number(l.quantity),
        unit_price_ht: Number(l.unit_price_ht),
        discount_type: l.discount_type ?? 'percentage',
        discount_value: Number(l.discount_value ?? 0),
        tax_rate: Number(l.tax_rate ?? 0),
    })),
});

const submit = () => form.put(route('sales.credit-notes.update', n.id));
</script>

<template>
    <AppLayout :title="`Modifier ${n.reference}`" :breadcrumbs="[
        { title: 'Ventes' }, { title: 'Avoirs', href: route('sales.credit-notes.index') },
        { title: n.reference, href: route('sales.credit-notes.show', n.id) }, { title: 'Modifier' },
    ]">
        <PageHeader :title="`Modifier ${n.reference}`" />
        <CreditNoteForm :form="form" :customers="customers" :invoices="invoices" :products="products" :taxes="taxes"
            submit-label="Enregistrer" :cancel-href="route('sales.credit-notes.show', n.id)" @submit="submit" />
    </AppLayout>
</template>
