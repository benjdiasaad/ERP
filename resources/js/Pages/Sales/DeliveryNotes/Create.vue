<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import DeliveryNoteForm from './Partials/DeliveryNoteForm.vue';

const props = defineProps({
    customers: { type: Array, required: true },
    products: { type: Array, required: true },
    defaults: { type: Object, required: true },
});

const customer = props.customers.find((c) => c.value === props.defaults.customer_id);
const form = useForm({
    customer_id: props.defaults.customer_id,
    date: props.defaults.date,
    expected_delivery_date: null,
    delivery_address: customer?.address ?? '',
    carrier: '',
    tracking_number: '',
    notes: '',
    lines: [{ product_id: null, description: '', ordered_quantity: 1, shipped_quantity: 1, unit: '' }],
});

const submit = () => form.post(route('sales.delivery-notes.store'));
</script>

<template>
    <AppLayout title="Nouveau bon de livraison"
        :breadcrumbs="[{ title: 'Ventes' }, { title: 'Bons de livraison', href: route('sales.delivery-notes.index') }, { title: 'Nouveau' }]">
        <PageHeader title="Nouveau bon de livraison" description="Pour une commande, utilisez plutôt « Bon de livraison » depuis la commande : les quantités sont reprises automatiquement." />
        <DeliveryNoteForm :form="form" :customers="customers" :products="products"
            submit-label="Créer le bon" :cancel-href="route('sales.delivery-notes.index')" @submit="submit" />
    </AppLayout>
</template>
