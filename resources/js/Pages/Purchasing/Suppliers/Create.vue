<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import SupplierForm from './Partials/SupplierForm.vue';

defineProps({
    paymentTerms: { type: Array, required: true },
});

const form = useForm({
    type: 'company', name: '', ice: '', rc: '', tax_id: '',
    email: '', phone: '', mobile: '', address: '', postal_code: '', city: '', country: 'MA',
    payment_term_id: null, bank_name: '', bank_account_number: '', bank_iban: '', bank_swift: '', notes: '', is_active: true,
});

const submit = () => form.post(route('purchasing.suppliers.store'));
</script>

<template>
    <AppLayout title="Nouveau fournisseur" :breadcrumbs="[{ title: 'Achats' }, { title: 'Fournisseurs', href: route('purchasing.suppliers.index') }, { title: 'Nouveau' }]">
        <PageHeader title="Nouveau fournisseur" />
        <SupplierForm :form="form" :payment-terms="paymentTerms" submit-label="Créer le fournisseur"
            :cancel-href="route('purchasing.suppliers.index')" @submit="submit" />
    </AppLayout>
</template>
