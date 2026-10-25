<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import SupplierForm from './Partials/SupplierForm.vue';

const props = defineProps({
    supplier: { type: Object, required: true },
    paymentTerms: { type: Array, required: true },
});

const s = props.supplier.data;
const form = useForm({
    type: s.type, name: s.name, ice: s.ice, rc: s.rc, tax_id: s.tax_id,
    email: s.email, phone: s.phone, mobile: s.mobile, address: s.address, postal_code: s.postal_code, city: s.city, country: s.country,
    payment_term_id: s.payment_term_id, bank_name: s.bank_name, bank_account_number: s.bank_account_number,
    bank_iban: s.bank_iban, bank_swift: s.bank_swift, notes: s.notes, is_active: s.is_active,
});

const submit = () => form.put(route('purchasing.suppliers.update', s.id));
</script>

<template>
    <AppLayout :title="`Modifier ${s.name}`" :breadcrumbs="[
        { title: 'Achats' }, { title: 'Fournisseurs', href: route('purchasing.suppliers.index') },
        { title: s.name, href: route('purchasing.suppliers.show', s.id) }, { title: 'Modifier' },
    ]">
        <PageHeader :title="`Modifier ${s.name}`" :description="s.code" />
        <SupplierForm :form="form" :payment-terms="paymentTerms" submit-label="Enregistrer"
            :cancel-href="route('purchasing.suppliers.show', s.id)" @submit="submit" />
    </AppLayout>
</template>
