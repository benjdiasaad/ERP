<script setup>
import { computed, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { labels } from '@/composables/format';

const props = defineProps({
    form: { type: Object, required: true },
    users: { type: Array, required: true },
    related: { type: Object, required: true },
    types: { type: Array, required: true },
    priorities: { type: Array, required: true },
    withStatus: { type: Boolean, default: false },
    submitLabel: { type: String, required: true },
    cancelHref: { type: String, required: true },
});

defineEmits(['submit']);

const relatedTypeLabels = { contact: 'Contact', lead: 'Lead', opportunity: 'Opportunité', customer: 'Client' };
const typeOptions = computed(() => props.types.map((t) => ({ value: t, label: labels.activityType[t] })));
const priorityOptions = computed(() => props.priorities.map((p) => ({ value: p, label: labels.priority[p] })));
const statusOptions = ['pending', 'in_progress', 'completed', 'cancelled'].map((s) => ({ value: s, label: labels.activityStatus[s] }));
const relatedTypeOptions = Object.entries(relatedTypeLabels).map(([value, label]) => ({ value, label }));
const relatedOptions = computed(() => props.related[props.form.related_type] ?? []);

// Changing the kind of record clears a selection that no longer applies.
watch(() => props.form.related_type, (type, previous) => {
    if (previous !== undefined && type !== previous) props.form.related_id = null;
});
</script>

<template>
    <form class="grid gap-6" @submit.prevent="$emit('submit')">
        <Card>
            <CardHeader><CardTitle>Activité</CardTitle></CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2">
                <FormField id="type" v-model="form.type" label="Type" :options="typeOptions" placeholder="Choisir…" required :error="form.errors.type" />
                <FormField id="priority" v-model="form.priority" label="Priorité" :options="priorityOptions" placeholder="Moyenne" :error="form.errors.priority" />
                <FormField id="subject" v-model="form.subject" label="Sujet" required class="sm:col-span-2" :error="form.errors.subject" />
                <FormField id="due_date" v-model="form.due_date" label="Échéance" type="datetime-local" :error="form.errors.due_date" />
                <FormField id="assigned_to" v-model="form.assigned_to" label="Responsable" :options="users" placeholder="Non assigné" :error="form.errors.assigned_to" />
                <FormField v-if="withStatus" id="status" v-model="form.status" label="Statut" :options="statusOptions" :error="form.errors.status" />
                <FormField id="description" v-model="form.description" label="Description" type="textarea" class="sm:col-span-2" :error="form.errors.description" />
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle>Lié à</CardTitle></CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2">
                <FormField id="related_type" v-model="form.related_type" label="Type de fiche" :options="relatedTypeOptions" placeholder="Aucune" :error="form.errors.related_type" />
                <FormField v-if="form.related_type" id="related_id" v-model="form.related_id" :label="relatedTypeLabels[form.related_type]"
                    :options="relatedOptions" placeholder="Choisir…" required :error="form.errors.related_id" />
            </CardContent>
        </Card>

        <div class="flex justify-end gap-2">
            <Button variant="outline" as-child><Link :href="cancelHref">Annuler</Link></Button>
            <Button type="submit" :disabled="form.processing">{{ submitLabel }}</Button>
        </div>
    </form>
</template>
