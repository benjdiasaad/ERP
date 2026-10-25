<script setup>
import { ref } from 'vue';
import { X } from '@lucide/vue';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger,
} from '@/components/ui/dialog';
import { useAction } from '@/composables/useAction';

const props = defineProps({
    quote: { type: Object, required: true },
});

const open = ref(false);
const reason = ref('');
const { run, processing, errors } = useAction();
const submit = () => run(route('sales.quotes.reject', props.quote.id), { reason: reason.value || null }, {
    fields: ['reason'],
    onSuccess: () => (open.value = false),
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button variant="outline"><X /> Refusé</Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Le client a refusé le devis</DialogTitle>
                <DialogDescription>{{ quote.reference }} · {{ quote.customer?.name }}</DialogDescription>
            </DialogHeader>
            <form class="grid gap-4" @submit.prevent="submit">
                <FormField id="reject_reason" v-model="reason" label="Motif (optionnel)" type="textarea" placeholder="Ex. : prix trop élevé, délai trop long" :error="errors.reason" />
                <DialogFooter>
                    <Button type="submit" variant="destructive" :disabled="processing">Confirmer le refus</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
