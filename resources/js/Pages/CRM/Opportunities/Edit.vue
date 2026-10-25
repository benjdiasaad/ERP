<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import OpportunityForm from './Partials/OpportunityForm.vue';

const props = defineProps({
    opportunity: { type: Object, required: true },
    contacts: { type: Array, required: true },
    customers: { type: Array, required: true },
    users: { type: Array, required: true },
});

const o = props.opportunity.data;
const form = useForm({
    title: o.title, customer_id: o.customer_id, contact_id: o.contact_id, assigned_to: o.assigned_to,
    description: o.description, value: o.value, probability: o.probability, expected_close_date: o.expected_close_date,
});

const submit = () => form.put(route('crm.opportunities.update', o.id));
</script>

<template>
    <AppLayout :title="`Modifier ${o.reference}`" :breadcrumbs="[
        { title: 'CRM' }, { title: 'Opportunités', href: route('crm.opportunities.index') },
        { title: o.reference, href: route('crm.opportunities.show', o.id) }, { title: 'Modifier' },
    ]">
        <PageHeader :title="`Modifier ${o.title}`" description="L'étape se change depuis la fiche (étape suivante, gagnée, perdue)." />
        <OpportunityForm :form="form" :contacts="contacts" :customers="customers" :users="users"
            submit-label="Enregistrer" :cancel-href="route('crm.opportunities.show', o.id)" @submit="submit" />
    </AppLayout>
</template>
