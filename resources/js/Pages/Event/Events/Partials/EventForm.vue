<script setup>
import { Link } from '@inertiajs/vue3';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { labels } from '@/composables/format';

defineProps({
    form: { type: Object, required: true },
    categories: { type: Array, required: true },
    submitLabel: { type: String, required: true },
    cancelHref: { type: String, required: true },
});

defineEmits(['submit']);

const typeOptions = Object.entries(labels.eventType).map(([value, label]) => ({ value, label }));
</script>

<template>
    <form class="grid gap-6" @submit.prevent="$emit('submit')">
        <Card>
            <CardHeader><CardTitle>Événement</CardTitle></CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-3">
                <FormField id="title" v-model="form.title" label="Titre" required class="sm:col-span-3" :error="form.errors.title" />
                <FormField id="event_category_id" v-model="form.event_category_id" label="Catégorie" :options="categories" placeholder="Choisir…" required :error="form.errors.event_category_id" />
                <FormField id="type" v-model="form.type" label="Type" :options="typeOptions" required :error="form.errors.type" />
                <FormField id="location" v-model="form.location" label="Lieu" :error="form.errors.location" />
                <FormField id="start_date" v-model="form.start_date" label="Début" type="datetime-local" required :error="form.errors.start_date" />
                <FormField id="end_date" v-model="form.end_date" label="Fin" type="datetime-local" required :error="form.errors.end_date" />
                <FormField id="budget" v-model="form.budget" label="Budget" type="number" :error="form.errors.budget" />
                <FormField id="description" v-model="form.description" label="Description" type="textarea" class="sm:col-span-3" :error="form.errors.description" />
                <div class="flex items-center gap-2">
                    <Checkbox id="is_mandatory" v-model="form.is_mandatory" />
                    <Label for="is_mandatory" class="font-normal">Présence obligatoire</Label>
                </div>
            </CardContent>
        </Card>

        <div class="flex justify-end gap-2">
            <Button variant="outline" as-child><Link :href="cancelHref">Annuler</Link></Button>
            <Button type="submit" :disabled="form.processing">{{ submitLabel }}</Button>
        </div>
    </form>
</template>
