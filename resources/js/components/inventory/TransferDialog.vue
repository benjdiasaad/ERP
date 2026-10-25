<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger,
} from '@/components/ui/dialog';

/**
 * Move a quantity of a product from one warehouse to another.
 */
const props = defineProps({
    products: { type: Array, required: true },
    warehouses: { type: Array, required: true },
});

const open = ref(false);
const form = useForm({ product_id: null, from_warehouse_id: props.warehouses[0]?.value ?? null, to_warehouse_id: props.warehouses[1]?.value ?? null, quantity: null, reference: '', notes: '' });
const unit = computed(() => props.products.find((p) => p.value === form.product_id)?.unit ?? '');

const submit = () => form.post(route('stock.movements.transfer'), {
    preserveScroll: true,
    onSuccess: () => {
        open.value = false;
        form.reset('product_id', 'quantity', 'reference', 'notes');
    },
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button variant="outline"><slot /></Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Transfert entre entrepôts</DialogTitle>
                <DialogDescription>Une sortie est enregistrée dans l'entrepôt d'origine et une entrée dans l'entrepôt de destination.</DialogDescription>
            </DialogHeader>
            <form class="grid gap-4" @submit.prevent="submit">
                <FormField id="transfer-product" v-model="form.product_id" label="Produit" :options="products" placeholder="Choisir un produit" required :error="form.errors.product_id" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField id="transfer-from" v-model="form.from_warehouse_id" label="Depuis" :options="warehouses" required :error="form.errors.from_warehouse_id" />
                    <FormField id="transfer-to" v-model="form.to_warehouse_id" label="Vers" :options="warehouses" required :error="form.errors.to_warehouse_id" />
                </div>
                <FormField id="transfer-quantity" v-model="form.quantity" :label="unit ? `Quantité (${unit})` : 'Quantité'" type="number" required :error="form.errors.quantity" />
                <FormField id="transfer-reference" v-model="form.reference" label="Référence" :error="form.errors.reference" />
                <DialogFooter>
                    <Button type="submit" :disabled="form.processing">Transférer</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
