<script setup>
import { computed, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Separator } from '@/components/ui/separator';
import { documentTotals, lineAmounts } from '@/composables/documentTotals';
import { formatMoney } from '@/composables/format';

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

const productsById = computed(() => Object.fromEntries(props.products.map((p) => [p.value, p])));
// VAT rates offered on lines (distinct, highest first).
const taxRates = computed(() => [...new Set([...props.taxes.map((t) => t.rate), 0])].sort((a, b) => b - a));
const totals = computed(() => documentTotals(props.form.lines));

const newLine = () => ({ product_id: null, description: '', quantity: 1, unit_price_ht: 0, discount_type: 'percentage', discount_value: 0, tax_rate: taxRates.value[0] ?? 20 });
const addLine = () => props.form.lines.push(newLine());
const removeLine = (index) => props.form.lines.splice(index, 1);

// Picking a product fills in its name, price and VAT rate.
const pickProduct = (line, productId) => {
    line.product_id = productId;
    const product = productsById.value[productId];
    if (!product) return;
    line.description = product.name;
    line.unit_price_ht = product.sale_price;
    line.tax_rate = product.tax_rate;
};

// Default the payment terms to the customer's.
watch(() => props.form.customer_id, (id) => {
    const customer = props.customers.find((c) => c.value === id);
    if (customer?.payment_term_id && !props.form.payment_term_id) props.form.payment_term_id = customer.payment_term_id;
});

const lineError = (index, field) => props.form.errors[`lines.${index}.${field}`];
</script>

<template>
    <form class="grid gap-6" @submit.prevent="$emit('submit')">
        <Card>
            <CardHeader><CardTitle>En-tête</CardTitle></CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <FormField id="customer_id" v-model="form.customer_id" label="Client" :options="customers" placeholder="Choisir un client…"
                    required class="sm:col-span-2 lg:col-span-1" :error="form.errors.customer_id" />
                <FormField id="date" v-model="form.date" label="Date du devis" type="date" required :error="form.errors.date" />
                <FormField id="validity_date" v-model="form.validity_date" label="Valable jusqu'au" type="date" required :error="form.errors.validity_date" />
                <FormField id="payment_term_id" v-model="form.payment_term_id" label="Conditions de paiement" :options="paymentTerms" placeholder="Aucune" :error="form.errors.payment_term_id" />
                <FormField id="currency_id" v-model="form.currency_id" label="Devise" :options="currencies" placeholder="Par défaut" :error="form.errors.currency_id" />
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Lignes</CardTitle>
                <CardAction><Button type="button" variant="outline" size="sm" @click="addLine"><Plus /> Ajouter une ligne</Button></CardAction>
            </CardHeader>
            <CardContent class="grid gap-4">
                <p v-if="form.errors.lines" class="text-sm text-destructive">{{ form.errors.lines }}</p>

                <div v-for="(line, index) in form.lines" :key="index" class="grid gap-3 rounded-lg border p-3">
                    <div class="grid gap-3 sm:grid-cols-[minmax(0,2fr)_minmax(0,3fr)_auto] sm:items-end">
                        <div class="grid gap-2">
                            <Label :for="`line-${index}-product`">Produit</Label>
                            <NativeSelect :id="`line-${index}-product`" :model-value="line.product_id" class="w-full" @update:model-value="(id) => pickProduct(line, id)">
                                <option :value="null">Ligne libre</option>
                                <option v-for="product in products" :key="product.value" :value="product.value">{{ product.label }}</option>
                            </NativeSelect>
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`line-${index}-description`">Description<span class="text-destructive">*</span></Label>
                            <Input :id="`line-${index}-description`" v-model="line.description" :aria-invalid="!!lineError(index, 'description') || undefined" />
                        </div>
                        <Button type="button" variant="ghost" size="icon" class="justify-self-end" :disabled="form.lines.length === 1"
                            :aria-label="`Supprimer la ligne ${index + 1}`" @click="removeLine(index)">
                            <Trash2 />
                        </Button>
                    </div>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-5 sm:items-end">
                        <div class="grid gap-2">
                            <Label :for="`line-${index}-quantity`">Quantité</Label>
                            <Input :id="`line-${index}-quantity`" v-model="line.quantity" type="number" min="0" step="0.01" :aria-invalid="!!lineError(index, 'quantity') || undefined" />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`line-${index}-price`">Prix unitaire HT</Label>
                            <Input :id="`line-${index}-price`" v-model="line.unit_price_ht" type="number" min="0" step="0.01" :aria-invalid="!!lineError(index, 'unit_price_ht') || undefined" />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`line-${index}-discount`">Remise (%)</Label>
                            <Input :id="`line-${index}-discount`" v-model="line.discount_value" type="number" min="0" max="100" step="0.01" />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`line-${index}-tax`">TVA</Label>
                            <NativeSelect :id="`line-${index}-tax`" v-model="line.tax_rate" class="w-full">
                                <option v-for="rate in taxRates" :key="rate" :value="rate">{{ rate }} %</option>
                            </NativeSelect>
                        </div>
                        <div class="col-span-2 grid gap-2 text-right sm:col-span-1">
                            <span class="text-sm font-medium">Total TTC</span>
                            <span class="h-9 content-center font-medium tabular-nums">{{ formatMoney(lineAmounts(line).total) }}</span>
                        </div>
                    </div>
                    <p v-for="field in ['description', 'quantity', 'unit_price_ht']" v-show="lineError(index, field)" :key="field" class="text-sm text-destructive">
                        {{ lineError(index, field) }}
                    </p>
                </div>

                <Separator />
                <dl class="ml-auto grid w-full max-w-xs gap-1.5 text-sm">
                    <div class="flex justify-between"><dt class="text-muted-foreground">Sous-total HT</dt><dd class="tabular-nums">{{ formatMoney(totals.subtotal) }}</dd></div>
                    <div v-if="totals.discount" class="flex justify-between"><dt class="text-muted-foreground">Remises</dt><dd class="tabular-nums">− {{ formatMoney(totals.discount) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted-foreground">TVA</dt><dd class="tabular-nums">{{ formatMoney(totals.tax) }}</dd></div>
                    <div class="flex justify-between border-t pt-1.5 text-base font-semibold"><dt>Total TTC</dt><dd class="tabular-nums">{{ formatMoney(totals.total) }}</dd></div>
                </dl>
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle>Notes</CardTitle></CardHeader>
            <CardContent>
                <FormField id="notes" v-model="form.notes" label="Notes visibles par le client" type="textarea" :error="form.errors.notes" />
            </CardContent>
        </Card>

        <div class="flex justify-end gap-2">
            <Button variant="outline" as-child><Link :href="cancelHref">Annuler</Link></Button>
            <Button type="submit" :disabled="form.processing">{{ submitLabel }}</Button>
        </div>
    </form>
</template>
