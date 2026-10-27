<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import PersonnelForm from './Partials/PersonnelForm.vue';

defineProps({
    departments: { type: Array, required: true },
    positions: { type: Array, required: true },
});

const form = useForm({
    last_name: '', first_name: '', matricule: '', gender: null, birth_date: null, national_id: '', social_security_number: '', nationality: 'Marocaine',
    department_id: null, position_id: null, employment_type: 'full_time', hire_date: new Date().toISOString().slice(0, 10), status: 'active', termination_date: null,
    email: '', phone: '', mobile: '', address: '', city: '', emergency_contact_name: '', emergency_contact_phone: '', emergency_contact_relation: '',
    bank_name: '', bank_account: '', bank_iban: '', notes: '',
});

const submit = () => form.transform((data) => ({ ...data, matricule: data.matricule || null })).post(route('organisation.personnels.store'));
</script>

<template>
    <AppLayout title="Nouveau collaborateur" :breadcrumbs="[{ title: 'Organisation' }, { title: 'Personnel', href: route('organisation.personnels.index') }, { title: 'Nouveau' }]">
        <PageHeader title="Nouveau collaborateur" />
        <PersonnelForm :form="form" :departments="departments" :positions="positions" submit-label="Créer la fiche"
            :cancel-href="route('organisation.personnels.index')" @submit="submit" />
    </AppLayout>
</template>
