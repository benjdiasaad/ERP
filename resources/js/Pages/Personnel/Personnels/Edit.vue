<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import PersonnelForm from './Partials/PersonnelForm.vue';

const props = defineProps({
    personnel: { type: Object, required: true },
    departments: { type: Array, required: true },
    positions: { type: Array, required: true },
});

const p = props.personnel.data;
const form = useForm({
    last_name: p.last_name,
    first_name: p.first_name,
    matricule: p.matricule,
    gender: p.gender,
    birth_date: p.birth_date,
    national_id: p.national_id,
    social_security_number: p.social_security_number,
    nationality: p.nationality,
    department_id: p.department_id,
    position_id: p.position_id,
    employment_type: p.employment_type,
    hire_date: p.hire_date,
    status: p.status,
    termination_date: p.termination_date,
    email: p.email,
    phone: p.phone,
    mobile: p.mobile,
    address: p.address,
    city: p.city,
    emergency_contact_name: p.emergency_contact_name,
    emergency_contact_phone: p.emergency_contact_phone,
    emergency_contact_relation: p.emergency_contact_relation,
    bank_name: p.bank_name,
    bank_account: p.bank_account,
    bank_iban: p.bank_iban,
    notes: p.notes,
});

const submit = () => form.put(route('organisation.personnels.update', p.id));
</script>

<template>
    <AppLayout :title="`Modifier ${p.first_name} ${p.last_name}`" :breadcrumbs="[
        { title: 'Organisation' }, { title: 'Personnel', href: route('organisation.personnels.index') },
        { title: `${p.last_name} ${p.first_name}`, href: route('organisation.personnels.show', p.id) }, { title: 'Modifier' },
    ]">
        <PageHeader :title="`Modifier ${p.first_name} ${p.last_name}`" :description="p.matricule" />
        <PersonnelForm :form="form" :departments="departments" :positions="positions" submit-label="Enregistrer"
            :cancel-href="route('organisation.personnels.show', p.id)" @submit="submit" />
    </AppLayout>
</template>
