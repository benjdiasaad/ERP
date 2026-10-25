<script setup>
import { ref } from 'vue';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger,
} from '@/components/ui/dialog';
import { useAction } from '@/composables/useAction';

/**
 * Button + dialog asking for a reason before a workflow action
 * (refuse a quote, cancel an order or invoice…). The trigger button
 * content is the default slot.
 */
const props = defineProps({
    url: { type: String, required: true },
    title: { type: String, required: true },
    description: { type: String, default: null },
    confirmLabel: { type: String, required: true },
    field: { type: String, default: 'reason' },
    fieldLabel: { type: String, default: 'Motif (optionnel)' },
    placeholder: { type: String, default: null },
    triggerVariant: { type: String, default: 'outline' },
    destructive: { type: Boolean, default: true },
});

const open = ref(false);
const value = ref('');
const { run, processing, errors } = useAction();

const submit = () => run(props.url, { [props.field]: value.value || null }, {
    fields: [props.field],
    onSuccess: () => {
        open.value = false;
        value.value = '';
    },
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button :variant="triggerVariant"><slot /></Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription v-if="description">{{ description }}</DialogDescription>
            </DialogHeader>
            <form class="grid gap-4" @submit.prevent="submit">
                <FormField :id="`reason-${field}`" v-model="value" :label="fieldLabel" type="textarea" :placeholder="placeholder" :error="errors[field]" />
                <DialogFooter>
                    <Button type="submit" :variant="destructive ? 'destructive' : 'default'" :disabled="processing">{{ confirmLabel }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
