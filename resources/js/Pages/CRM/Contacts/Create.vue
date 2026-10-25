<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import ContactForm from './Partials/ContactForm.vue';

defineProps({
    customers: { type: Array, required: true },
    suppliers: { type: Array, required: true },
    sources: { type: Array, required: true },
});

const form = useForm({
    first_name: '', last_name: '', job_title: '', organization: '',
    email: '', phone: '', mobile: '', address: '', city: '', country: 'MA',
    customer_id: null, supplier_id: null, source: null, notes: '', is_active: true,
});

const submit = () => form.post(route('crm.contacts.store'));
</script>

<template>
    <AppLayout title="Nouveau contact"
        :breadcrumbs="[{ title: 'CRM' }, { title: 'Contacts', href: route('crm.contacts.index') }, { title: 'Nouveau' }]">
        <PageHeader title="Nouveau contact" />
        <ContactForm :form="form" :customers="customers" :suppliers="suppliers" :sources="sources"
            submit-label="Créer le contact" :cancel-href="route('crm.contacts.index')" @submit="submit" />
    </AppLayout>
</template>
