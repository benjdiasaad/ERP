<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import BankAccountForm from './Partials/BankAccountForm.vue';

const props = defineProps({
    currencies: { type: Array, required: true },
    defaultCurrency: { type: Number, default: null },
});

const form = useForm({
    name: '', bank: '', account_number: '', iban: '', swift: '',
    currency_id: props.defaultCurrency ?? props.currencies[0]?.value ?? null, balance: 0, is_active: true,
});

const submit = () => form.post(route('finance.bank-accounts.store'));
</script>

<template>
    <AppLayout title="Nouveau compte bancaire" :breadcrumbs="[{ title: 'Finance' }, { title: 'Comptes bancaires', href: route('finance.bank-accounts.index') }, { title: 'Nouveau' }]">
        <PageHeader title="Nouveau compte bancaire" />
        <BankAccountForm :form="form" :currencies="currencies" with-balance submit-label="Créer le compte"
            :cancel-href="route('finance.bank-accounts.index')" @submit="submit" />
    </AppLayout>
</template>
