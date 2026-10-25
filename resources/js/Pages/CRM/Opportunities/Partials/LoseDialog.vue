<script setup>
import { reactive, ref } from 'vue';
import { ThumbsDown } from '@lucide/vue';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger,
} from '@/components/ui/dialog';
import { useAction } from '@/composables/useAction';

const props = defineProps({
    opportunity: { type: Object, required: true },
});

const open = ref(false);
const form = reactive({ lost_reason: '', actual_close_date: new Date().toISOString().slice(0, 10) });
const { run, processing, errors } = useAction();
const submit = () => run(route('crm.opportunities.lose', props.opportunity.id), form, {
    fields: ['lost_reason', 'actual_close_date'],
    onSuccess: () => (open.value = false),
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button variant="outline"><ThumbsDown /> Perdue</Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Marquer comme perdue</DialogTitle>
                <DialogDescription>{{ opportunity.reference }} · {{ opportunity.title }}</DialogDescription>
            </DialogHeader>
            <form class="grid gap-4" @submit.prevent="submit">
                <FormField id="lose_reason" v-model="form.lost_reason" label="Raison" type="textarea" required
                    placeholder="Ex. : offre concurrente moins chère" :error="errors.lost_reason" />
                <FormField id="lose_date" v-model="form.actual_close_date" label="Date" type="date" :error="errors.actual_close_date" />
                <DialogFooter>
                    <Button type="submit" variant="destructive" :disabled="processing">Confirmer la perte</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
