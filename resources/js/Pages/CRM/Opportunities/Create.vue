<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import OpportunityForm from './Partials/OpportunityForm.vue';

const props = defineProps({
    contacts: { type: Array, required: true },
    customers: { type: Array, required: true },
    users: { type: Array, required: true },
    stages: { type: Array, required: true },
    defaults: { type: Object, required: true },
});

const form = useForm({
    title: '', customer_id: props.defaults.customer_id, contact_id: props.defaults.contact_id, stage: 'prospecting',
    assigned_to: null, description: '', value: null, probability: null, expected_close_date: null,
});

const submit = () => form.post(route('crm.opportunities.store'));
</script>

<template>
    <AppLayout title="Nouvelle opportunité"
        :breadcrumbs="[{ title: 'CRM' }, { title: 'Opportunités', href: route('crm.opportunities.index') }, { title: 'Nouvelle' }]">
        <PageHeader title="Nouvelle opportunité" />
        <OpportunityForm :form="form" :contacts="contacts" :customers="customers" :users="users" :stages="stages"
            submit-label="Créer l'opportunité" :cancel-href="route('crm.opportunities.index')" @submit="submit" />
    </AppLayout>
</template>
