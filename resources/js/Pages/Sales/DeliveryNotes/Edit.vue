<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import DeliveryNoteForm from './Partials/DeliveryNoteForm.vue';

const props = defineProps({
    note: { type: Object, required: true },
    customers: { type: Array, required: true },
    products: { type: Array, required: true },
    warehouses: { type: Array, required: true },
});

const n = props.note.data;
const form = useForm({
    customer_id: n.customer_id,
    date: n.date,
    expected_delivery_date: n.expected_delivery_date,
    delivery_address: n.delivery_address,
    carrier: n.carrier,
    tracking_number: n.tracking_number,
    warehouse_id: n.warehouse_id,
    notes: n.notes,
    lines: n.lines.map((l) => ({
        id: l.id,
        sales_order_line_id: l.sales_order_line_id,
        product_id: l.product_id,
        description: l.description,
        ordered_quantity: Number(l.ordered_quantity),
        shipped_quantity: Number(l.shipped_quantity),
        unit: l.unit,
    })),
});

const submit = () => form.put(route('sales.delivery-notes.update', n.id));
</script>

<template>
    <AppLayout :title="`Modifier ${n.reference}`" :breadcrumbs="[
        { title: 'Ventes' }, { title: 'Bons de livraison', href: route('sales.delivery-notes.index') },
        { title: n.reference, href: route('sales.delivery-notes.show', n.id) }, { title: 'Modifier' },
    ]">
        <PageHeader :title="`Modifier ${n.reference}`" />
        <DeliveryNoteForm :form="form" :customers="customers" :products="products" :warehouses="warehouses"
            submit-label="Enregistrer" :cancel-href="route('sales.delivery-notes.show', n.id)" @submit="submit" />
    </AppLayout>
</template>
