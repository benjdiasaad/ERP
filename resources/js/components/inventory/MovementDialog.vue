<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger,
} from '@/components/ui/dialog';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';

/**
 * Manual stock entry, exit or adjustment (set the counted quantity).
 * The trigger button content is the default slot.
 */
const props = defineProps({
    products: { type: Array, required: true },
    warehouses: { type: Array, required: true },
});

const open = ref(false);
const form = useForm({ type: 'in', product_id: null, warehouse_id: props.warehouses[0]?.value ?? null, quantity: null, reference: '', notes: '' });

const unit = computed(() => props.products.find((p) => p.value === form.product_id)?.unit ?? '');
const quantityLabel = computed(() => (form.type === 'adjustment' ? 'Nouvelle quantité en stock' : 'Quantité'));
const hint = {
    in: 'Ajoute la quantité au stock (achat hors bon de commande, production…).',
    out: 'Retire la quantité du stock (consommation, casse, perte…).',
    adjustment: 'Remplace la quantité en stock par la quantité saisie.',
};

const submit = () => form.post(route('stock.movements.store'), {
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
            <Button><slot /></Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Nouveau mouvement</DialogTitle>
                <DialogDescription>{{ hint[form.type] }}</DialogDescription>
            </DialogHeader>
            <form class="grid gap-4" @submit.prevent="submit">
                <Tabs v-model="form.type">
                    <TabsList class="w-full">
                        <TabsTrigger value="in">Entrée</TabsTrigger>
                        <TabsTrigger value="out">Sortie</TabsTrigger>
                        <TabsTrigger value="adjustment">Ajustement</TabsTrigger>
                    </TabsList>
                </Tabs>
                <FormField id="movement-product" v-model="form.product_id" label="Produit" :options="products" placeholder="Choisir un produit" required :error="form.errors.product_id" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField id="movement-warehouse" v-model="form.warehouse_id" label="Entrepôt" :options="warehouses" required :error="form.errors.warehouse_id" />
                    <FormField id="movement-quantity" v-model="form.quantity" :label="unit ? `${quantityLabel} (${unit})` : quantityLabel" type="number" required :error="form.errors.quantity" />
                </div>
                <FormField id="movement-reference" v-model="form.reference" label="Référence" placeholder="N° de bon, motif court…" :error="form.errors.reference" />
                <FormField id="movement-notes" v-model="form.notes" label="Notes" type="textarea" :error="form.errors.notes" />
                <p v-if="form.errors.type" class="text-sm text-destructive">{{ form.errors.type }}</p>
                <DialogFooter>
                    <Button type="submit" :disabled="form.processing">Enregistrer</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
