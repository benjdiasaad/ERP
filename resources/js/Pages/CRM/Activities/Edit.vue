<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import ActivityForm from './Partials/ActivityForm.vue';
import { toLocalDateTimeInput, toServerDateTime } from '@/composables/format';

const props = defineProps({
    activity: { type: Object, required: true },
    users: { type: Array, required: true },
    related: { type: Object, required: true },
    types: { type: Array, required: true },
    priorities: { type: Array, required: true },
});

const a = props.activity.data;
const form = useForm({
    type: a.type, subject: a.subject, priority: a.priority, status: a.status, due_date: toLocalDateTimeInput(a.due_date),
    assigned_to: a.assigned_to, description: a.description, related_type: a.related_type, related_id: a.related_id,
});

const submit = () => form.transform((data) => ({ ...data, due_date: toServerDateTime(data.due_date) }))
    .put(route('crm.activities.update', a.id));
</script>

<template>
    <AppLayout title="Modifier l'activité" :breadcrumbs="[
        { title: 'CRM' }, { title: 'Activités', href: route('crm.activities.index') }, { title: 'Modifier' },
    ]">
        <PageHeader :title="`Modifier « ${a.subject} »`" />
        <ActivityForm :form="form" :users="users" :related="related" :types="types" :priorities="priorities" with-status
            submit-label="Enregistrer" :cancel-href="route('crm.activities.index')" @submit="submit" />
    </AppLayout>
</template>
