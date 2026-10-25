<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import ActivityForm from './Partials/ActivityForm.vue';
import { toServerDateTime } from '@/composables/format';

const props = defineProps({
    users: { type: Array, required: true },
    related: { type: Object, required: true },
    types: { type: Array, required: true },
    priorities: { type: Array, required: true },
    defaults: { type: Object, required: true },
    back: { type: String, required: true },
});

const form = useForm({
    type: 'call', subject: '', priority: 'medium', due_date: null, assigned_to: null, description: '',
    related_type: props.defaults.related_type, related_id: props.defaults.related_id,
});

const submit = () => form.transform((data) => ({ ...data, due_date: toServerDateTime(data.due_date) }))
    .post(route('crm.activities.store'));
</script>

<template>
    <AppLayout title="Nouvelle activité"
        :breadcrumbs="[{ title: 'CRM' }, { title: 'Activités', href: route('crm.activities.index') }, { title: 'Nouvelle' }]">
        <PageHeader title="Nouvelle activité" />
        <ActivityForm :form="form" :users="users" :related="related" :types="types" :priorities="priorities"
            submit-label="Enregistrer" :cancel-href="back" @submit="submit" />
    </AppLayout>
</template>
