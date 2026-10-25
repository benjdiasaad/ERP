<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { labels } from '@/composables/format';

const props = defineProps({
    form: { type: Object, required: true },
    contacts: { type: Array, required: true },
    users: { type: Array, required: true },
    sources: { type: Array, required: true },
    statusOptions: { type: Array, required: true },
    submitLabel: { type: String, required: true },
    cancelHref: { type: String, required: true },
});

defineEmits(['submit']);

const sourceOptions = computed(() => props.sources.map((s) => ({ value: s, label: labels.source[s] ?? s })));
const statuses = computed(() => props.statusOptions.map((s) => ({ value: s, label: labels.leadStatus[s] ?? s })));
</script>

<template>
    <form class="grid gap-6" @submit.prevent="$emit('submit')">
        <Card>
            <CardHeader><CardTitle>Lead</CardTitle></CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2">
                <FormField id="title" v-model="form.title" label="Titre" required class="sm:col-span-2" :error="form.errors.title" />
                <FormField id="contact_id" v-model="form.contact_id" label="Contact" :options="contacts" placeholder="Aucun" :error="form.errors.contact_id" />
                <FormField id="source" v-model="form.source" label="Source" :options="sourceOptions" placeholder="Non renseignée" :error="form.errors.source" />
                <FormField id="status" v-model="form.status" label="Statut" :options="statuses" placeholder="Nouveau" :error="form.errors.status" />
                <FormField id="assigned_to" v-model="form.assigned_to" label="Responsable" :options="users" placeholder="Non assigné" :error="form.errors.assigned_to" />
                <FormField v-if="form.status === 'lost'" id="lost_reason" v-model="form.lost_reason" label="Raison de la perte" type="textarea"
                    class="sm:col-span-2" required :error="form.errors.lost_reason" />
                <FormField id="description" v-model="form.description" label="Description" type="textarea" class="sm:col-span-2" :error="form.errors.description" />
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle>Estimation</CardTitle></CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-3">
                <FormField id="estimated_value" v-model="form.estimated_value" label="Valeur estimée (MAD)" type="number" :error="form.errors.estimated_value" />
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
