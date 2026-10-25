<script setup>
import { ref } from 'vue';
import { ShoppingCart } from '@lucide/vue';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger,
} from '@/components/ui/dialog';
import { useAction } from '@/composables/useAction';

const props = defineProps({
    request: { type: Object, required: true },
    suppliers: { type: Array, required: true },
});

const open = ref(false);
const supplierId = ref(props.request.supplier_id);
const { run, processing, errors } = useAction();
const submit = () => run(route('purchasing.requests.convert', props.request.id), { supplier_id: supplierId.value }, { fields: ['supplier_id'] });
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button><ShoppingCart /> Commander</Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Créer le bon de commande</DialogTitle>
                <DialogDescription>Les articles et prix estimés sont repris dans un bon de commande brouillon que vous pourrez ajuster.</DialogDescription>
            </DialogHeader>
            <form class="grid gap-4" @submit.prevent="submit">
                <FormField id="convert_supplier" v-model="supplierId" label="Fournisseur" :options="suppliers" placeholder="Choisir…" required :error="errors.supplier_id" />
                <DialogFooter>
                    <Button type="submit" :disabled="processing || !supplierId">Créer le bon de commande</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
