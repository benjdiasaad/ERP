<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import LeadForm from './Partials/LeadForm.vue';

const props = defineProps({
    contacts: { type: Array, required: true },
    users: { type: Array, required: true },
    sources: { type: Array, required: true },
    defaults: { type: Object, required: true },
});

const form = useForm({
    title: '', contact_id: props.defaults.contact_id, source: null, status: 'new', assigned_to: null,
    description: '', estimated_value: null, probability: null, expected_close_date: null,
});

const submit = () => form.post(route('crm.leads.store'));
</script>

<template>
    <AppLayout title="Nouveau lead" :breadcrumbs="[{ title: 'CRM' }, { title: 'Leads', href: route('crm.leads.index') }, { title: 'Nouveau' }]">
        <PageHeader title="Nouveau lead" />
        <LeadForm :form="form" :contacts="contacts" :users="users" :sources="sources" :status-options="['new', 'contacted']"
            submit-label="Créer le lead" :cancel-href="route('crm.leads.index')" @submit="submit" />
    </AppLayout>
</template>
