<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import JournalEntryForm from './Partials/JournalEntryForm.vue';

const props = defineProps({
    accounts: { type: Array, required: true },
    today: { type: String, required: true },
});

const form = useForm({
    date: props.today,
    reference: '',
    description: '',
    lines: [
        { chart_of_account_id: null, description: '', debit: 0, credit: 0 },
        { chart_of_account_id: null, description: '', debit: 0, credit: 0 },
    ],
});

const submit = () => form.transform((data) => ({ ...data, reference: data.reference || null })).post(route('finance.journal-entries.store'));
</script>

<template>
    <AppLayout title="Nouvelle écriture" :breadcrumbs="[{ title: 'Finance' }, { title: 'Écritures', href: route('finance.journal-entries.index') }, { title: 'Nouvelle' }]">
        <PageHeader title="Nouvelle écriture" description="L'écriture est enregistrée en brouillon ; comptabilisez-la une fois équilibrée." />
        <JournalEntryForm :form="form" :accounts="accounts" submit-label="Enregistrer le brouillon"
            :cancel-href="route('finance.journal-entries.index')" @submit="submit" />
    </AppLayout>
</template>
