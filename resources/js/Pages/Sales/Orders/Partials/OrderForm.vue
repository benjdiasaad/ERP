<script setup>
import { watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import FormField from '@/components/FormField.vue';
import DocumentLinesEditor from '@/components/sales/DocumentLinesEditor.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

const props = defineProps({
    form: { type: Object, required: true },
    customers: { type: Array, required: true },
    products: { type: Array, required: true },
    taxes: { type: Array, required: true },
    paymentTerms: { type: Array, required: true },
    currencies: { type: Array, required: true },
    submitLabel: { type: String, required: true },
    cancelHref: { type: String, required: true },
});

defineEmits(['submit']);

// Default payment terms and delivery address from the customer.
watch(() => props.form.customer_id, (id) => {
    const customer = props.customers.find((c) => c.value === id);
    if (!customer) return;
    if (customer.payment_term_id && !props.form.payment_term_id) props.form.payment_term_id = customer.payment_term_id;
    if (customer.address && !props.form.delivery_address) props.form.delivery_address = customer.address;
});
</script>

<template>
    <form class="grid gap-6" @submit.prevent="$emit('submit')">
        <Card>
            <CardHeader><CardTitle>En-tête</CardTitle></CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <FormField id="customer_id" v-model="form.customer_id" label="Client" :options="customers" placeholder="Choisir un client…"
                    required class="sm:col-span-2 lg:col-span-1" :error="form.errors.customer_id" />
                <FormField id="order_date" v-model="form.order_date" label="Date de commande" type="date" required :error="form.errors.order_date" />
                <FormField id="expected_delivery_date" v-model="form.expected_delivery_date" label="Livraison prévue" type="date" :error="form.errors.expected_delivery_date" />
                <FormField id="delivery_address" v-model="form.delivery_address" label="Adresse de livraison" class="sm:col-span-2 lg:col-span-3" :error="form.errors.delivery_address" />
                <FormField id="payment_term_id" v-model="form.payment_term_id" label="Conditions de paiement" :options="paymentTerms" placeholder="Aucune" :error="form.errors.payment_term_id" />
                <FormField id="currency_id" v-model="form.currency_id" label="Devise" :options="currencies" placeholder="Par défaut" :error="form.errors.currency_id" />
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
