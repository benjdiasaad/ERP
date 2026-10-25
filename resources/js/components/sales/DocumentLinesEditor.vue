<script setup>
import { computed } from 'vue';
import { Plus, Trash2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Separator } from '@/components/ui/separator';
import { documentTotals, lineAmounts } from '@/composables/documentTotals';
import { formatMoney } from '@/composables/format';

/**
 * Editable lines of a sales document (quote, order, invoice) with live totals.
 * `lines` is mutated in place (it is an Inertia form field).
 */
const props = defineProps({
    lines: { type: Array, required: true },
    products: { type: Array, required: true },
    taxes: { type: Array, required: true },
    errors: { type: Object, default: () => ({}) },
});

const productsById = computed(() => Object.fromEntries(props.products.map((p) => [p.value, p])));
// VAT rates offered on lines (distinct, highest first).
const taxRates = computed(() => [...new Set([...props.taxes.map((t) => t.rate), 0])].sort((a, b) => b - a));
const totals = computed(() => documentTotals(props.lines));

const addLine = () => props.lines.push({
    product_id: null, description: '', quantity: 1, unit_price_ht: 0, discount_type: 'percentage', discount_value: 0, tax_rate: taxRates.value[0] ?? 20,
});
const removeLine = (index) => props.lines.splice(index, 1);

// Picking a product fills in its name, price and VAT rate.
const pickProduct = (line, productId) => {
    line.product_id = productId;
    const product = productsById.value[productId];
    if (!product) return;
    line.description = product.name;
    line.unit_price_ht = product.sale_price;
    line.tax_rate = product.tax_rate;
};

const lineError = (index, field) => props.errors[`lines.${index}.${field}`];
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Lignes</CardTitle>
            <CardAction><Button type="button" variant="outline" size="sm" @click="addLine"><Plus /> Ajouter une ligne</Button></CardAction>
        </CardHeader>
        <CardContent class="grid gap-4">
            <p v-if="errors.lines" class="text-sm text-destructive">{{ errors.lines }}</p>

            <div v-for="(line, index) in lines" :key="index" class="grid gap-3 rounded-lg border p-3">
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
                    <Button type="button" variant="ghost" size="icon" class="justify-self-end" :disabled="lines.length === 1"
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
</template>
