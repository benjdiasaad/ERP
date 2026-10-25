<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { labels } from '@/composables/format';

const props = defineProps({
    form: { type: Object, required: true },
    customers: { type: Array, required: true },
    suppliers: { type: Array, required: true },
    sources: { type: Array, required: true },
    submitLabel: { type: String, required: true },
    cancelHref: { type: String, required: true },
});

defineEmits(['submit']);

const customerOptions = computed(() => props.customers.map((c) => ({ value: c.id, label: c.name })));
const supplierOptions = computed(() => props.suppliers.map((s) => ({ value: s.id, label: s.name })));
const sourceOptions = computed(() => props.sources.map((s) => ({ value: s, label: labels.source[s] ?? s })));
</script>

<template>
    <form class="grid gap-6" @submit.prevent="$emit('submit')">
        <Card>
            <CardHeader>
                <CardTitle>Identité</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2">
                <FormField id="first_name" v-model="form.first_name" label="Prénom" required :error="form.errors.first_name" />
                <FormField id="last_name" v-model="form.last_name" label="Nom" required :error="form.errors.last_name" />
                <FormField id="job_title" v-model="form.job_title" label="Fonction" :error="form.errors.job_title" />
                <FormField id="organization" v-model="form.organization" label="Organisation" :error="form.errors.organization" />
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Coordonnées</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-3">
                <FormField id="email" v-model="form.email" label="E-mail" type="email" :error="form.errors.email" />
                <FormField id="phone" v-model="form.phone" label="Téléphone" :error="form.errors.phone" />
                <FormField id="mobile" v-model="form.mobile" label="Mobile" :error="form.errors.mobile" />
                <FormField id="address" v-model="form.address" label="Adresse" class="sm:col-span-3" :error="form.errors.address" />
                <FormField id="city" v-model="form.city" label="Ville" :error="form.errors.city" />
                <FormField id="country" v-model="form.country" label="Pays" :error="form.errors.country" />
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Relation</CardTitle>
                <CardDescription>Lien avec un client ou un fournisseur existant.</CardDescription>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-3">
                <FormField id="customer_id" v-model="form.customer_id" label="Client lié" :options="customerOptions" placeholder="Aucun" :error="form.errors.customer_id" />
                <FormField id="supplier_id" v-model="form.supplier_id" label="Fournisseur lié" :options="supplierOptions" placeholder="Aucun" :error="form.errors.supplier_id" />
                <FormField id="source" v-model="form.source" label="Source" :options="sourceOptions" placeholder="Non renseignée" :error="form.errors.source" />
                <FormField id="notes" v-model="form.notes" label="Notes" type="textarea" class="sm:col-span-3" :error="form.errors.notes" />
                <div class="flex items-center gap-2">
                    <Checkbox id="is_active" v-model="form.is_active" />
                    <Label for="is_active" class="font-normal">Contact actif</Label>
                </div>
            </CardContent>
        </Card>

        <div class="flex justify-end gap-2">
            <Button variant="outline" as-child><Link :href="cancelHref">Annuler</Link></Button>
            <Button type="submit" :disabled="form.processing">{{ submitLabel }}</Button>
        </div>
    </form>
</template>
