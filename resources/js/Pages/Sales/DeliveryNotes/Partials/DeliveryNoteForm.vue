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

const props = defineProps({
    form: { type: Object, required: true },
    customers: { type: Array, required: true },
    products: { type: Array, required: true },
    submitLabel: { type: String, required: true },
    cancelHref: { type: String, required: true },
});

defineEmits(['submit']);

const productsById = computed(() => Object.fromEntries(props.products.map((p) => [p.value, p])));

const addLine = () => props.form.lines.push({ product_id: null, description: '', ordered_quantity: 1, shipped_quantity: 1, unit: '' });
const removeLine = (index) => props.form.lines.splice(index, 1);
const pickProduct = (line, productId) => {
    line.product_id = productId;
    const product = productsById.value[productId];
    if (!product) return;
    line.description = product.name;
    line.unit = product.unit ?? '';
};

watch(() => props.form.customer_id, (id) => {
    const customer = props.customers.find((c) => c.value === id);
    if (customer?.address && !props.form.delivery_address) props.form.delivery_address = customer.address;
});

const lineError = (index, field) => props.form.errors[`lines.${index}.${field}`];
</script>

<template>
    <form class="grid gap-6" @submit.prevent="$emit('submit')">
        <Card>
            <CardHeader><CardTitle>Livraison</CardTitle></CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <FormField id="customer_id" v-model="form.customer_id" label="Client" :options="customers" placeholder="Choisir un client…"
                    required class="sm:col-span-2 lg:col-span-1" :error="form.errors.customer_id" />
                <FormField id="date" v-model="form.date" label="Date" type="date" :error="form.errors.date" />
                <FormField id="expected_delivery_date" v-model="form.expected_delivery_date" label="Livraison prévue" type="date" :error="form.errors.expected_delivery_date" />
                <FormField id="delivery_address" v-model="form.delivery_address" label="Adresse de livraison" class="sm:col-span-2 lg:col-span-3" :error="form.errors.delivery_address" />
                <FormField id="carrier" v-model="form.carrier" label="Transporteur" :error="form.errors.carrier" />
                <FormField id="tracking_number" v-model="form.tracking_number" label="N° de suivi" :error="form.errors.tracking_number" />
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Articles</CardTitle>
                <CardAction><Button type="button" variant="outline" size="sm" @click="addLine"><Plus /> Ajouter un article</Button></CardAction>
            </CardHeader>
            <CardContent class="grid gap-4">
                <p v-if="form.errors.lines" class="text-sm text-destructive">{{ form.errors.lines }}</p>
                <div v-for="(line, index) in form.lines" :key="index" class="grid gap-3 rounded-lg border p-3 sm:grid-cols-[minmax(0,2fr)_minmax(0,3fr)_6rem_6rem_5rem_auto] sm:items-end">
                    <div class="grid gap-2">
                        <Label :for="`dl-${index}-product`">Produit</Label>
                        <NativeSelect :id="`dl-${index}-product`" :model-value="line.product_id" class="w-full" @update:model-value="(id) => pickProduct(line, id)">
                            <option :value="null">Article libre</option>
                            <option v-for="product in products" :key="product.value" :value="product.value">{{ product.label }}</option>
                        </NativeSelect>
                    </div>
                    <div class="grid gap-2">
                        <Label :for="`dl-${index}-description`">Description<span class="text-destructive">*</span></Label>
                        <Input :id="`dl-${index}-description`" v-model="line.description" :aria-invalid="!!lineError(index, 'description') || undefined" />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="`dl-${index}-ordered`">Commandé</Label>
                        <Input :id="`dl-${index}-ordered`" v-model="line.ordered_quantity" type="number" min="0" step="0.01" />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="`dl-${index}-shipped`">À livrer</Label>
                        <Input :id="`dl-${index}-shipped`" v-model="line.shipped_quantity" type="number" min="0" step="0.01" :aria-invalid="!!lineError(index, 'shipped_quantity') || undefined" />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="`dl-${index}-unit`">Unité</Label>
                        <Input :id="`dl-${index}-unit`" v-model="line.unit" />
                    </div>
                    <Button type="button" variant="ghost" size="icon" :disabled="form.lines.length === 1" :aria-label="`Supprimer l'article ${index + 1}`" @click="removeLine(index)">
                        <Trash2 />
                    </Button>
                    <p v-for="field in ['description', 'ordered_quantity', 'shipped_quantity']" v-show="lineError(index, field)" :key="field" class="text-sm text-destructive sm:col-span-6">
                        {{ lineError(index, field) }}
                    </p>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle>Notes</CardTitle></CardHeader>
            <CardContent>
                <FormField id="notes" v-model="form.notes" label="Instructions de livraison" type="textarea" :error="form.errors.notes" />
            </CardContent>
        </Card>

        <div class="flex justify-end gap-2">
            <Button variant="outline" as-child><Link :href="cancelHref">Annuler</Link></Button>
            <Button type="submit" :disabled="form.processing">{{ submitLabel }}</Button>
        </div>
    </form>
</template>
