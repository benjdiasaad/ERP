<script setup>
import { watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import FormField from '@/components/FormField.vue';
import DocumentLinesEditor from '@/components/sales/DocumentLinesEditor.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

const props = defineProps({
    form: { type: Object, required: true },
    suppliers: { type: Array, required: true },
    products: { type: Array, required: true },
    taxes: { type: Array, required: true },
    paymentTerms: { type: Array, required: true },
    currencies: { type: Array, required: true },
    submitLabel: { type: String, required: true },
    cancelHref: { type: String, required: true },
});

defineEmits(['submit']);

const addDays = (date, days) => {
    const d = new Date(`${date}T00:00:00`);
    d.setDate(d.getDate() + days);
    return d.toISOString().slice(0, 10);
};

watch(() => props.form.supplier_id, (id) => {
    const supplier = props.suppliers.find((s) => s.value === id);
    if (supplier?.payment_term_id && !props.form.payment_term_id) props.form.payment_term_id = supplier.payment_term_id;
});
watch(() => [props.form.payment_term_id, props.form.invoice_date], ([termId, date]) => {
    const term = props.paymentTerms.find((t) => t.value === termId);
    if (term && date) props.form.due_date = addDays(date, term.days);
});
</script>

<template>
    <form class="grid gap-6" @submit.prevent="$emit('submit')">
        <Card>
            <CardHeader><CardTitle>En-tête</CardTitle></CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <FormField id="supplier_id" v-model="form.supplier_id" label="Fournisseur" :options="suppliers" placeholder="Choisir un fournisseur…"
                    required class="sm:col-span-2 lg:col-span-1" :error="form.errors.supplier_id" />
                <FormField id="invoice_date" v-model="form.invoice_date" label="Date de facture" type="date" required :error="form.errors.invoice_date" />
                <FormField id="due_date" v-model="form.due_date" label="Échéance" type="date" :error="form.errors.due_date" />
                <FormField id="payment_term_id" v-model="form.payment_term_id" label="Conditions de paiement" :options="paymentTerms" placeholder="Aucune" :error="form.errors.payment_term_id" />
                <FormField id="currency_id" v-model="form.currency_id" label="Devise" :options="currencies" placeholder="Par défaut" :error="form.errors.currency_id" />
            </CardContent>
        </Card>

        <DocumentLinesEditor :lines="form.lines" :products="products" :taxes="taxes" :errors="form.errors" />

        <Card>
            <CardHeader><CardTitle>Notes</CardTitle></CardHeader>
            <CardContent>
                <FormField id="notes" v-model="form.notes" label="Notes internes (n° de facture fournisseur…)" type="textarea" :error="form.errors.notes" />
            </CardContent>
        </Card>

        <div class="flex justify-end gap-2">
            <Button variant="outline" as-child><Link :href="cancelHref">Annuler</Link></Button>
            <Button type="submit" :disabled="form.processing">{{ submitLabel }}</Button>
        </div>
    </form>
</template>
