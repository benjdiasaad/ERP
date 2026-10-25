<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import RequestForm from './Partials/RequestForm.vue';

const props = defineProps({
    request: { type: Object, required: true },
    suppliers: { type: Array, required: true },
    products: { type: Array, required: true },
});

const r = props.request.data;
const form = useForm({
    title: r.title, priority: r.priority, required_date: r.required_date, supplier_id: r.supplier_id, description: r.description,
    lines: r.lines.map((l) => ({ description: l.description, quantity: Number(l.quantity), unit: l.unit, estimated_unit_price: Number(l.estimated_unit_price) })),
});

const submit = () => form.put(route('purchasing.requests.update', r.id));
</script>

<template>
    <AppLayout :title="`Modifier ${r.reference}`" :breadcrumbs="[
        { title: 'Achats' }, { title: 'Demandes d\'achat', href: route('purchasing.requests.index') },
        { title: r.reference, href: route('purchasing.requests.show', r.id) }, { title: 'Modifier' },
    ]">
        <PageHeader :title="`Modifier ${r.reference}`" />
        <RequestForm :form="form" :suppliers="suppliers" submit-label="Enregistrer" :cancel-href="route('purchasing.requests.show', r.id)" @submit="submit" />
    </AppLayout>
</template>
