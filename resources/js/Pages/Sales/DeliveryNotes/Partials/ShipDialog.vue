<script setup>
import { reactive, ref } from 'vue';
import { Truck } from '@lucide/vue';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger,
} from '@/components/ui/dialog';
import { useAction } from '@/composables/useAction';

const props = defineProps({
    note: { type: Object, required: true },
});

const open = ref(false);
const form = reactive({
    shipped_at: new Date().toISOString().slice(0, 10),
    carrier: props.note.carrier ?? '',
    tracking_number: props.note.tracking_number ?? '',
});
const { run, processing, errors } = useAction();
const submit = () => run(route('sales.delivery-notes.ship', props.note.id), form, {
    fields: ['shipped_at', 'carrier', 'tracking_number'],
    onSuccess: () => (open.value = false),
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button><Truck /> Expédier</Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Enregistrer l'expédition</DialogTitle>
                <DialogDescription>{{ note.reference }} · {{ note.customer?.name }}</DialogDescription>
            </DialogHeader>
            <form class="grid gap-4" @submit.prevent="submit">
                <FormField id="ship_date" v-model="form.shipped_at" label="Date d'expédition" type="date" :error="errors.shipped_at" />
                <FormField id="ship_carrier" v-model="form.carrier" label="Transporteur" placeholder="Ex. : CTM Messagerie, Amana" :error="errors.carrier" />
                <FormField id="ship_tracking" v-model="form.tracking_number" label="N° de suivi" :error="errors.tracking_number" />
                <DialogFooter>
                    <Button type="submit" :disabled="processing">Confirmer l'expédition</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
