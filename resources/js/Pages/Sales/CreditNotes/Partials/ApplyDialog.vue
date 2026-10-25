<script setup>
import { ref } from 'vue';
import { ArrowDownToLine } from '@lucide/vue';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger,
} from '@/components/ui/dialog';
import { useAction } from '@/composables/useAction';
import { formatMoney } from '@/composables/format';

const props = defineProps({
    note: { type: Object, required: true },
    invoices: { type: Array, required: true },
});

const open = ref(false);
const invoiceId = ref(props.note.invoice_id ?? props.invoices[0]?.value ?? null);
const { run, processing, errors } = useAction();
const submit = () => run(route('sales.credit-notes.apply', props.note.id), { invoice_id: invoiceId.value }, {
    fields: ['invoice_id'],
    onSuccess: () => (open.value = false),
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button><ArrowDownToLine /> Imputer sur une facture</Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Imputer l'avoir</DialogTitle>
                <DialogDescription>{{ formatMoney(note.total_ttc) }} seront déduits du reste dû de la facture choisie.</DialogDescription>
            </DialogHeader>
            <form class="grid gap-4" @submit.prevent="submit">
                <FormField v-if="invoices.length" id="apply_invoice" v-model="invoiceId" label="Facture" :options="invoices" placeholder="Choisir…" required :error="errors.invoice_id" />
                <p v-else class="text-sm text-muted-foreground">Ce client n'a aucune facture envoyée à laquelle imputer l'avoir.</p>
                <DialogFooter>
                    <Button type="submit" :disabled="processing || !invoiceId">Imputer</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
