<script setup>
import { reactive, ref } from 'vue';
import { Trophy } from '@lucide/vue';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger,
} from '@/components/ui/dialog';
import { useAction } from '@/composables/useAction';

const props = defineProps({
    opportunity: { type: Object, required: true },
    quotes: { type: Array, required: true },
});

const open = ref(false);
const form = reactive({
    value: props.opportunity.value,
    actual_close_date: new Date().toISOString().slice(0, 10),
    won_quote_id: null,
});
const { run, processing, errors } = useAction();
const submit = () => run(route('crm.opportunities.win', props.opportunity.id), form, {
    fields: ['value', 'actual_close_date', 'won_quote_id'],
    onSuccess: () => (open.value = false),
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button><Trophy /> Gagnée</Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Marquer comme gagnée</DialogTitle>
                <DialogDescription>{{ opportunity.reference }} · {{ opportunity.title }}</DialogDescription>
            </DialogHeader>
            <form class="grid gap-4" @submit.prevent="submit">
                <FormField id="win_value" v-model="form.value" label="Montant signé (MAD)" type="number" :error="errors.value" />
                <FormField id="win_date" v-model="form.actual_close_date" label="Date de signature" type="date" :error="errors.actual_close_date" />
                <FormField v-if="quotes.length" id="win_quote" v-model="form.won_quote_id" label="Devis accepté" :options="quotes"
                    placeholder="Aucun" :error="errors.won_quote_id" />
                <DialogFooter>
                    <Button type="submit" :disabled="processing">Confirmer</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
