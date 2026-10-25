<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import ContactForm from './Partials/ContactForm.vue';

const props = defineProps({
    contact: { type: Object, required: true },
    customers: { type: Array, required: true },
    suppliers: { type: Array, required: true },
    sources: { type: Array, required: true },
});

const c = props.contact.data;
const form = useForm({
    first_name: c.first_name, last_name: c.last_name, job_title: c.job_title, organization: c.organization,
    email: c.email, phone: c.phone, mobile: c.mobile, address: c.address, city: c.city, country: c.country,
    customer_id: c.customer_id, supplier_id: c.supplier_id, source: c.source, notes: c.notes, is_active: c.is_active,
});

const submit = () => form.put(route('crm.contacts.update', c.id));
</script>

<template>
    <AppLayout :title="`Modifier ${c.full_name}`" :breadcrumbs="[
        { title: 'CRM' },
        { title: 'Contacts', href: route('crm.contacts.index') },
        { title: c.full_name, href: route('crm.contacts.show', c.id) },
        { title: 'Modifier' },
    ]">
        <PageHeader :title="`Modifier ${c.full_name}`" />
        <ContactForm :form="form" :customers="customers" :suppliers="suppliers" :sources="sources"
            submit-label="Enregistrer" :cancel-href="route('crm.contacts.show', c.id)" @submit="submit" />
    </AppLayout>
</template>
