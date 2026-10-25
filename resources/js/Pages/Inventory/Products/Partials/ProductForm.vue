<script setup>
import { Link } from '@inertiajs/vue3';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { labels } from '@/composables/format';

defineProps({
    form: { type: Object, required: true },
    categories: { type: Array, required: true },
    submitLabel: { type: String, required: true },
    cancelHref: { type: String, required: true },
});

defineEmits(['submit']);

const typeOptions = Object.entries(labels.productType).map(([value, label]) => ({ value, label }));
const flags = [
    ['is_purchasable', 'Peut être acheté'],
    ['is_sellable', 'Peut être vendu'],
    ['is_stockable', 'Géré en stock'],
    ['is_active', 'Produit actif'],
];
</script>

<template>
    <form class="grid gap-6" @submit.prevent="$emit('submit')">
        <Card>
            <CardHeader><CardTitle>Identification</CardTitle></CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-3">
                <FormField id="name" v-model="form.name" label="Désignation" required class="sm:col-span-2" :error="form.errors.name" />
                <FormField id="code" v-model="form.code" label="Code" placeholder="Généré automatiquement" :error="form.errors.code" />
                <FormField id="type" v-model="form.type" label="Type" :options="typeOptions" :error="form.errors.type" />
                <FormField id="category_id" v-model="form.category_id" label="Catégorie" :options="categories" placeholder="Aucune" :error="form.errors.category_id" />
                <FormField id="unit" v-model="form.unit" label="Unité" placeholder="pièce, kg, m…" :error="form.errors.unit" />
                <FormField id="barcode" v-model="form.barcode" label="Code-barres" :error="form.errors.barcode" />
                <FormField id="description" v-model="form.description" label="Description" type="textarea" class="sm:col-span-3" :error="form.errors.description" />
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle>Prix</CardTitle></CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-3">
                <FormField id="purchase_price" v-model="form.purchase_price" label="Prix d'achat HT" type="number" :error="form.errors.purchase_price" />
                <FormField id="sale_price" v-model="form.sale_price" label="Prix de vente HT" type="number" :error="form.errors.sale_price" />
                <FormField id="tax_rate" v-model="form.tax_rate" label="TVA (%)" type="number" :error="form.errors.tax_rate" />
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Stock</CardTitle>
                <CardDescription>Le point de commande signale le produit en stock bas.</CardDescription>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-3">
                <FormField id="min_stock_level" v-model="form.min_stock_level" label="Stock minimum" type="number" :error="form.errors.min_stock_level" />
                <FormField id="reorder_point" v-model="form.reorder_point" label="Point de commande" type="number" :error="form.errors.reorder_point" />
                <FormField id="max_stock_level" v-model="form.max_stock_level" label="Stock maximum" type="number" :error="form.errors.max_stock_level" />
                <div class="grid gap-3 sm:col-span-3 sm:grid-cols-4">
                    <div v-for="[field, text] in flags" :key="field" class="flex items-center gap-2">
                        <Checkbox :id="field" v-model="form[field]" />
                        <Label :for="field" class="font-normal">{{ text }}</Label>
                    </div>
                </div>
                <FormField id="notes" v-model="form.notes" label="Notes" type="textarea" class="sm:col-span-3" :error="form.errors.notes" />
            </CardContent>
        </Card>

        <div class="flex justify-end gap-2">
            <Button variant="outline" as-child><Link :href="cancelHref">Annuler</Link></Button>
            <Button type="submit" :disabled="form.processing">{{ submitLabel }}</Button>
        </div>
    </form>
</template>
