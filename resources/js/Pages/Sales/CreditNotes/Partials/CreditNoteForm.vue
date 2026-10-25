<script setup>
import { computed, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import FormField from '@/components/FormField.vue';
import DocumentLinesEditor from '@/components/sales/DocumentLinesEditor.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

const props = defineProps({
    form: { type: Object, required: true },
    customers: { type: Array, required: true },
    invoices: { type: Array, required: true },
    products: { type: Array, required: true },
    taxes: { type: Array, required: true },
    submitLabel: { type: String, required: true },
    cancelHref: { type: String, required: true },
});

defineEmits(['submit']);

// Only the selected customer's invoices can be referenced.
const customerInvoices = computed(() => props.invoices.filter((i) => i.customer_id === props.form.customer_id));
watch(() => props.form.customer_id, () => {
    if (!customerInvoices.value.some((i) => i.value === props.form.invoice_id)) props.form.invoice_id = null;
});
</script>

<template>
    <form class="grid gap-6" @submit.prevent="$emit('submit')">
        <Card>
            <CardHeader>
                <CardTitle>Avoir</CardTitle>
                <CardDescription>Pour annuler une facture entière, utilisez plutôt « Avoir » depuis la facture.</CardDescription>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <FormField id="customer_id" v-model="form.customer_id" label="Client" :options="customers" placeholder="Choisir un client…"
                    required class="sm:col-span-2 lg:col-span-1" :error="form.errors.customer_id" />
                <FormField id="invoice_id" v-model="form.invoice_id" label="Facture concernée" :options="customerInvoices"
                    :placeholder="form.customer_id ? 'Aucune' : 'Choisissez d’abord le client'" :error="form.errors.invoice_id" />
                <FormField id="date" v-model="form.date" label="Date" type="date" :error="form.errors.date" />
                <FormField id="reason" v-model="form.reason" label="Motif" class="sm:col-span-2 lg:col-span-3" placeholder="Ex. : retour de marchandise, remise commerciale" :error="form.errors.reason" />
            </CardContent>
        </Card>

        <DocumentLinesEditor :lines="form.lines" :products="products" :taxes="taxes" :errors="form.errors" />

        <Card>
            <CardHeader><CardTitle>Notes</CardTitle></CardHeader>
            <CardContent>
                <FormField id="notes" v-model="form.notes" label="Notes" type="textarea" :error="form.errors.notes" />
            </CardContent>
        </Card>

        <div class="flex justify-end gap-2">
            <Button variant="outline" as-child><Link :href="cancelHref">Annuler</Link></Button>
            <Button type="submit" :disabled="form.processing">{{ submitLabel }}</Button>
        </div>
    </form>
</template>
