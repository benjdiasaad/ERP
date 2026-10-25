<script setup>
import { reactive, ref, watch } from 'vue';
import { Banknote } from '@lucide/vue';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger,
} from '@/components/ui/dialog';
import { useAction } from '@/composables/useAction';
import { formatMoney, labels } from '@/composables/format';

const props = defineProps({
    invoice: { type: Object, required: true },
});

const open = ref(false);
const form = reactive({
    amount: Number(props.invoice.amount_due),
    payment_date: new Date().toISOString().slice(0, 10),
    payment_method: 'bank_transfer',
    reference: '',
});
const methods = Object.entries(labels.paymentMethod).map(([value, label]) => ({ value, label }));

// Re-open with the current amount due (it changes after a partial payment).
watch(open, (isOpen) => {
    if (isOpen) form.amount = Number(props.invoice.amount_due);
});

const { run, processing, errors } = useAction();
const submit = () => run(route('sales.invoices.record-payment', props.invoice.id), form, {
    fields: ['amount', 'payment_date', 'payment_method', 'reference'],
    onSuccess: () => (open.value = false),
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button><Banknote /> Enregistrer un paiement</Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Enregistrer un paiement</DialogTitle>
                <DialogDescription>{{ invoice.reference }} · reste dû {{ formatMoney(invoice.amount_due) }}</DialogDescription>
            </DialogHeader>
            <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                <FormField id="payment_amount" v-model="form.amount" label="Montant (MAD)" type="number" required :error="errors.amount" />
                <FormField id="payment_date" v-model="form.payment_date" label="Date" type="date" :error="errors.payment_date" />
                <FormField id="payment_method" v-model="form.payment_method" label="Mode de paiement" :options="methods" :error="errors.payment_method" />
                <FormField id="payment_reference" v-model="form.reference" label="Référence (n° chèque, virement…)" :error="errors.reference" />
                <DialogFooter class="sm:col-span-2">
                    <Button type="submit" :disabled="processing">Enregistrer</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
