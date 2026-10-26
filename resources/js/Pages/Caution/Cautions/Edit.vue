<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import CautionForm from './Partials/CautionForm.vue';
import { label } from '@/composables/format';

const props = defineProps({
    caution: { type: Object, required: true },
    types: { type: Array, required: true },
    customers: { type: Array, required: true },
    suppliers: { type: Array, required: true },
});

const c = props.caution.data;
const form = useForm({
    direction: c.direction,
    caution_type_id: c.caution_type_id,
    partner_type: c.partner_alias,
    partner_id: c.partner_id,
    amount: Number(c.amount),
    issue_date: c.issue_date,
    expiry_date: c.expiry_date,
    document_reference: c.document_reference, bank_name: c.bank_name, bank_account: c.bank_account,
    bank_reference: c.bank_reference, notes: c.notes,
});

// Direction and partner are fixed once the caution exists.
const submit = () => form.transform(({ direction, partner_type, partner_id, ...data }) => data).put(route('finance.cautions.update', c.id));
const partnerLabel = c.partner ? `${label('cautionPartner', c.partner_alias)} · ${c.partner.name}` : null;
</script>

<template>
    <AppLayout title="Modifier la caution" :breadcrumbs="[
        { title: 'Finance' }, { title: 'Cautions', href: route('finance.cautions.index') },
        { title: c.document_reference ?? `Caution #${c.id}`, href: route('finance.cautions.show', c.id) }, { title: 'Modifier' },
    ]">
        <PageHeader title="Modifier la caution" :description="`${label('cautionDirection', c.direction)} · ${c.caution_type?.name ?? ''}`" />
        <CautionForm :form="form" :types="types" :customers="customers" :suppliers="suppliers" locked-party :partner-label="partnerLabel"
            submit-label="Enregistrer" :cancel-href="route('finance.cautions.show', c.id)" @submit="submit" />
    </AppLayout>
</template>
