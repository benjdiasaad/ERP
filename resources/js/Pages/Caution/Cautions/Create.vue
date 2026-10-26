<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import CautionForm from './Partials/CautionForm.vue';

const props = defineProps({
    types: { type: Array, required: true },
    customers: { type: Array, required: true },
    suppliers: { type: Array, required: true },
    defaults: { type: Object, required: true },
});

const form = useForm({
    direction: props.defaults.direction,
    caution_type_id: props.types[0]?.value ?? null,
    partner_type: props.defaults.partner_type,
    partner_id: props.defaults.partner_id,
    amount: null,
    issue_date: props.defaults.issue_date,
    expiry_date: props.defaults.expiry_date,
    document_reference: '', bank_name: '', bank_account: '', bank_reference: '', notes: '',
});

const submit = () => form.post(route('finance.cautions.store'));
</script>

<template>
    <AppLayout title="Nouvelle caution" :breadcrumbs="[{ title: 'Finance' }, { title: 'Cautions', href: route('finance.cautions.index') }, { title: 'Nouvelle' }]">
        <PageHeader title="Nouvelle caution" description="La caution est enregistrée en brouillon ; activez-la une fois émise par la banque." />
        <CautionForm :form="form" :types="types" :customers="customers" :suppliers="suppliers" submit-label="Enregistrer la caution"
            :cancel-href="route('finance.cautions.index')" @submit="submit" />
    </AppLayout>
</template>
