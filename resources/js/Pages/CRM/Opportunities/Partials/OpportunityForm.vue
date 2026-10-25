<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { formatCurrency, labels } from '@/composables/format';

const props = defineProps({
    form: { type: Object, required: true },
    contacts: { type: Array, required: true },
    customers: { type: Array, required: true },
    users: { type: Array, required: true },
    stages: { type: Array, default: null },
    submitLabel: { type: String, required: true },
    cancelHref: { type: String, required: true },
});

defineEmits(['submit']);

const stageOptions = computed(() => (props.stages ?? []).map((s) => ({ value: s, label: labels.stage[s] })));
const weighted = computed(() => (Number(props.form.value) || 0) * (Number(props.form.probability) || 0) / 100);
</script>

<template>
    <form class="grid gap-6" @submit.prevent="$emit('submit')">
        <Card>
            <CardHeader><CardTitle>Opportunité</CardTitle></CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2">
                <FormField id="title" v-model="form.title" label="Titre" required class="sm:col-span-2" :error="form.errors.title" />
                <FormField id="customer_id" v-model="form.customer_id" label="Client" :options="customers" placeholder="Aucun" :error="form.errors.customer_id" />
                <FormField id="contact_id" v-model="form.contact_id" label="Contact" :options="contacts" placeholder="Aucun" :error="form.errors.contact_id" />
                <FormField v-if="stages" id="stage" v-model="form.stage" label="Étape" :options="stageOptions" :error="form.errors.stage" />
                <FormField id="assigned_to" v-model="form.assigned_to" label="Responsable" :options="users" placeholder="Non assigné" :error="form.errors.assigned_to" />
                <FormField id="description" v-model="form.description" label="Description" type="textarea" class="sm:col-span-2" :error="form.errors.description" />
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Valeur</CardTitle>
                <CardDescription>
                    Laissez la probabilité vide pour appliquer celle de l'étape. Valeur pondérée : {{ formatCurrency(weighted) }}
                </CardDescription>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-3">
                <FormField id="value" v-model="form.value" label="Valeur (MAD)" type="number" :error="form.errors.value" />
                <FormField id="probability" v-model="form.probability" label="Probabilité (%)" type="number" :error="form.errors.probability" />
                <FormField id="expected_close_date" v-model="form.expected_close_date" label="Clôture prévue" type="date" :error="form.errors.expected_close_date" />
            </CardContent>
        </Card>

        <div class="flex justify-end gap-2">
            <Button variant="outline" as-child><Link :href="cancelHref">Annuler</Link></Button>
            <Button type="submit" :disabled="form.processing">{{ submitLabel }}</Button>
        </div>
    </form>
</template>
