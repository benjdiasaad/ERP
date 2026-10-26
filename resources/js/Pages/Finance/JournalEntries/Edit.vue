<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import JournalEntryForm from './Partials/JournalEntryForm.vue';

const props = defineProps({
    entry: { type: Object, required: true },
    accounts: { type: Array, required: true },
});

const e = props.entry.data;
const form = useForm({
    date: e.date,
    reference: e.reference,
    description: e.description ?? '',
    lines: e.lines.map((l) => ({
        id: l.id, chart_of_account_id: l.chart_of_account_id, description: l.description ?? '', debit: Number(l.debit), credit: Number(l.credit),
    })),
});

const submit = () => form.put(route('finance.journal-entries.update', e.id));
</script>

<template>
    <AppLayout :title="`Modifier ${e.reference}`" :breadcrumbs="[
        { title: 'Finance' }, { title: 'Écritures', href: route('finance.journal-entries.index') },
        { title: e.reference, href: route('finance.journal-entries.show', e.id) }, { title: 'Modifier' },
    ]">
        <PageHeader :title="`Modifier ${e.reference}`" />
        <JournalEntryForm :form="form" :accounts="accounts" submit-label="Enregistrer"
            :cancel-href="route('finance.journal-entries.show', e.id)" @submit="submit" />
    </AppLayout>
</template>
