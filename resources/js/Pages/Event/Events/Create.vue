<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import EventForm from './Partials/EventForm.vue';
import { toServerDateTime } from '@/composables/format';

const props = defineProps({
    categories: { type: Array, required: true },
});

const day = new Date().toISOString().slice(0, 10);
const form = useForm({
    title: '', event_category_id: props.categories[0]?.value ?? null, type: 'meeting', location: '',
    start_date: `${day}T09:00`, end_date: `${day}T10:00`, budget: null, description: '', is_mandatory: false,
});

const submit = () => form.transform((data) => ({ ...data, start_date: toServerDateTime(data.start_date), end_date: toServerDateTime(data.end_date) }))
    .post(route('organisation.events.store'));
</script>

<template>
    <AppLayout title="Nouvel événement" :breadcrumbs="[{ title: 'Organisation' }, { title: 'Événements', href: route('organisation.events.index') }, { title: 'Nouveau' }]">
        <PageHeader title="Nouvel événement" />
        <EventForm :form="form" :categories="categories" submit-label="Créer l'événement" :cancel-href="route('organisation.events.index')" @submit="submit" />
    </AppLayout>
</template>
