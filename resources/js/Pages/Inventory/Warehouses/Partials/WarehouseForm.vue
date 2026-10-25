<script setup>
import { Link } from '@inertiajs/vue3';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

defineProps({
    form: { type: Object, required: true },
    managers: { type: Array, required: true },
    submitLabel: { type: String, required: true },
    cancelHref: { type: String, required: true },
});

defineEmits(['submit']);
</script>

<template>
    <form class="grid gap-6" @submit.prevent="$emit('submit')">
        <Card>
            <CardHeader><CardTitle>Entrepôt</CardTitle></CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-3">
                <FormField id="name" v-model="form.name" label="Nom" required class="sm:col-span-2" :error="form.errors.name" />
                <FormField id="code" v-model="form.code" label="Code" placeholder="Généré automatiquement" :error="form.errors.code" />
                <FormField id="manager_id" v-model="form.manager_id" label="Responsable" :options="managers" placeholder="Aucun" :error="form.errors.manager_id" />
                <div class="flex items-center gap-2 sm:col-span-2 sm:pt-6">
                    <div class="flex items-center gap-2">
                        <Checkbox id="is_default" v-model="form.is_default" />
                        <Label for="is_default" class="font-normal">Entrepôt par défaut</Label>
                    </div>
                    <div class="ml-6 flex items-center gap-2">
                        <Checkbox id="is_active" v-model="form.is_active" />
                        <Label for="is_active" class="font-normal">Actif</Label>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle>Adresse</CardTitle></CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-3">
                <FormField id="address" v-model="form.address" label="Adresse" class="sm:col-span-3" :error="form.errors.address" />
                <FormField id="postal_code" v-model="form.postal_code" label="Code postal" :error="form.errors.postal_code" />
                <FormField id="city" v-model="form.city" label="Ville" :error="form.errors.city" />
                <FormField id="country" v-model="form.country" label="Pays" :error="form.errors.country" />
                <FormField id="notes" v-model="form.notes" label="Notes" type="textarea" class="sm:col-span-3" :error="form.errors.notes" />
            </CardContent>
        </Card>

        <div class="flex justify-end gap-2">
            <Button variant="outline" as-child><Link :href="cancelHref">Annuler</Link></Button>
            <Button type="submit" :disabled="form.processing">{{ submitLabel }}</Button>
        </div>
    </form>
</template>
