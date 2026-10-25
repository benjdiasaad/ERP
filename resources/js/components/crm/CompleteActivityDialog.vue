<script setup>
import { ref } from 'vue';
import { CheckCircle2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useAction } from '@/composables/useAction';

const props = defineProps({
    activity: { type: Object, required: true },
    size: { type: String, default: 'sm' },
});

const open = ref(false);
const outcome = ref(props.activity.outcome ?? '');
const { run, processing, errors } = useAction();

const submit = () => run(route('crm.activities.complete', props.activity.id), { outcome: outcome.value || null }, {
    fields: ['outcome'],
    onSuccess: () => (open.value = false),
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button variant="outline" :size="size"><CheckCircle2 /> Terminer</Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Terminer l'activité</DialogTitle>
                <DialogDescription>{{ activity.subject }}</DialogDescription>
            </DialogHeader>
            <form class="grid gap-2" @submit.prevent="submit">
                <Label for="outcome">Résultat (optionnel)</Label>
                <Textarea id="outcome" v-model="outcome" rows="3" placeholder="Ex. : client intéressé, devis à envoyer." />
                <p v-if="errors.outcome" class="text-sm text-destructive">{{ errors.outcome }}</p>
                <DialogFooter class="mt-2">
                    <Button type="submit" :disabled="processing">Marquer comme terminée</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
