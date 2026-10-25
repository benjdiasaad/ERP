<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import LeadForm from './Partials/LeadForm.vue';

const props = defineProps({
    lead: { type: Object, required: true },
    contacts: { type: Array, required: true },
    users: { type: Array, required: true },
    sources: { type: Array, required: true },
});

const l = props.lead.data;
const form = useForm({
    title: l.title, contact_id: l.contact_id, source: l.source, status: l.status, assigned_to: l.assigned_to,
    lost_reason: l.lost_reason, description: l.description, estimated_value: l.estimated_value,
    probability: l.probability, expected_close_date: l.expected_close_date,
});

// "qualified" is set through the Qualify action, so it is only offered when already in that state.
const statusOptions = ['new', 'contacted', ...(l.status === 'qualified' ? ['qualified'] : []), 'unqualified', 'lost'];

const submit = () => form.transform((data) => (data.status === 'qualified' ? { ...data, status: undefined } : data))
    .put(route('crm.leads.update', l.id));
</script>

<template>
    <AppLayout :title="`Modifier ${l.reference}`" :breadcrumbs="[
        { title: 'CRM' }, { title: 'Leads', href: route('crm.leads.index') },
        { title: l.reference, href: route('crm.leads.show', l.id) }, { title: 'Modifier' },
    ]">
        <PageHeader :title="`Modifier ${l.title}`" />
        <LeadForm :form="form" :contacts="contacts" :users="users" :sources="sources" :status-options="statusOptions"
            submit-label="Enregistrer" :cancel-href="route('crm.leads.show', l.id)" @submit="submit" />
    </AppLayout>
</template>
