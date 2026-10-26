<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import BankAccountForm from './Partials/BankAccountForm.vue';

const props = defineProps({
    account: { type: Object, required: true },
    currencies: { type: Array, required: true },
});

const a = props.account.data;
const form = useForm({
    name: a.name, bank: a.bank, account_number: a.account_number, iban: a.iban, swift: a.swift,
    currency_id: a.currency_id, is_active: a.is_active,
});

const submit = () => form.put(route('finance.bank-accounts.update', a.id));
</script>

<template>
    <AppLayout :title="`Modifier ${a.name}`" :breadcrumbs="[
        { title: 'Finance' }, { title: 'Comptes bancaires', href: route('finance.bank-accounts.index') },
        { title: a.name, href: route('finance.bank-accounts.show', a.id) }, { title: 'Modifier' },
    ]">
        <PageHeader :title="`Modifier ${a.name}`" :description="a.bank" />
        <BankAccountForm :form="form" :currencies="currencies" submit-label="Enregistrer"
            :cancel-href="route('finance.bank-accounts.show', a.id)" @submit="submit" />
    </AppLayout>
</template>
