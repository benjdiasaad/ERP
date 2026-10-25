<script setup>
import { reactive, ref } from 'vue';
import { ArrowRightLeft } from '@lucide/vue';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger,
} from '@/components/ui/dialog';
import { useAction } from '@/composables/useAction';
import { labels } from '@/composables/format';

const props = defineProps({
    lead: { type: Object, required: true },
    customers: { type: Array, required: true },
    defaultCustomerId: { type: Number, default: null },
});

const open = ref(false);
const form = reactive({
    title: props.lead.title,
    value: props.lead.estimated_value,
    customer_id: props.defaultCustomerId,
    stage: 'prospecting',
    expected_close_date: props.lead.expected_close_date,
});
const stageOptions = ['prospecting', 'qualification', 'proposal', 'negotiation'].map((s) => ({ value: s, label: labels.stage[s] }));
const { run, processing, errors } = useAction();

const fields = ['title', 'value', 'customer_id', 'stage', 'expected_close_date'];
const submit = () => run(route('crm.leads.convert', props.lead.id), form, { fields });
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button><ArrowRightLeft /> Convertir</Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Convertir en opportunité</DialogTitle>
                <DialogDescription>Le lead passera au statut « Converti » et une opportunité sera créée avec ces informations.</DialogDescription>
            </DialogHeader>
            <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                <FormField id="convert_title" v-model="form.title" label="Titre de l'opportunité" class="sm:col-span-2" :error="errors.title" />
                <FormField id="convert_value" v-model="form.value" label="Valeur (MAD)" type="number" :error="errors.value" />
                <FormField id="convert_stage" v-model="form.stage" label="Étape de départ" :options="stageOptions" :error="errors.stage" />
                <FormField id="convert_customer" v-model="form.customer_id" label="Client" :options="customers" placeholder="Aucun" :error="errors.customer_id" />
                <FormField id="convert_close" v-model="form.expected_close_date" label="Clôture prévue" type="date" :error="errors.expected_close_date" />
                <DialogFooter class="sm:col-span-2">
                    <Button type="submit" :disabled="processing">Créer l'opportunité</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
