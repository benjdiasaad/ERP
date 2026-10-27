<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import EventForm from './Partials/EventForm.vue';
import { toLocalDateTimeInput, toServerDateTime } from '@/composables/format';

const props = defineProps({
    event: { type: Object, required: true },
    categories: { type: Array, required: true },
});

const e = props.event.data;
const form = useForm({
    title: e.title, event_category_id: e.event_category_id, type: e.type, location: e.location,
    start_date: toLocalDateTimeInput(e.start_date), end_date: toLocalDateTimeInput(e.end_date),
    budget: e.budget ? Number(e.budget) : null, description: e.description, is_mandatory: !!e.is_mandatory,
});

const submit = () => form.transform((data) => ({ ...data, start_date: toServerDateTime(data.start_date), end_date: toServerDateTime(data.end_date) }))
    .put(route('organisation.events.update', e.id));
</script>

<template>
    <AppLayout :title="`Modifier ${e.title}`" :breadcrumbs="[
        { title: 'Organisation' }, { title: 'Événements', href: route('organisation.events.index') },
        { title: e.title, href: route('organisation.events.show', e.id) }, { title: 'Modifier' },
    ]">
        <PageHeader :title="`Modifier ${e.title}`" :description="e.reference" />
        <EventForm :form="form" :categories="categories" submit-label="Enregistrer" :cancel-href="route('organisation.events.show', e.id)" @submit="submit" />
    </AppLayout>
</template>
