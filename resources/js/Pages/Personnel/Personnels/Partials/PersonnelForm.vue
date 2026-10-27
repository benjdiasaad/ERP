<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { labels } from '@/composables/format';

const props = defineProps({
    form: { type: Object, required: true },
    departments: { type: Array, required: true },
    positions: { type: Array, required: true },
    submitLabel: { type: String, required: true },
    cancelHref: { type: String, required: true },
});

defineEmits(['submit']);

const options = (group) => Object.entries(labels[group]).map(([value, label]) => ({ value, label }));
// Positions of the chosen department (all positions when none is chosen).
const positionOptions = computed(() => props.positions.filter((p) => !props.form.department_id || p.department_id === props.form.department_id));
</script>

<template>
    <form class="grid gap-6" @submit.prevent="$emit('submit')">
        <Card>
            <CardHeader><CardTitle>Identité</CardTitle></CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-3">
                <FormField id="last_name" v-model="form.last_name" label="Nom" required :error="form.errors.last_name" />
                <FormField id="first_name" v-model="form.first_name" label="Prénom" required :error="form.errors.first_name" />
                <FormField id="matricule" v-model="form.matricule" label="Matricule" placeholder="Généré automatiquement" :error="form.errors.matricule" />
                <FormField id="gender" v-model="form.gender" label="Genre" :options="options('gender')" :error="form.errors.gender" />
                <FormField id="birth_date" v-model="form.birth_date" label="Date de naissance" type="date" :error="form.errors.birth_date" />
                <FormField id="national_id" v-model="form.national_id" label="CIN" :error="form.errors.national_id" />
                <FormField id="social_security_number" v-model="form.social_security_number" label="N° CNSS" :error="form.errors.social_security_number" />
                <FormField id="nationality" v-model="form.nationality" label="Nationalité" :error="form.errors.nationality" />
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle>Emploi</CardTitle></CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-3">
                <FormField id="department_id" v-model="form.department_id" label="Département" :options="departments" placeholder="Aucun" :error="form.errors.department_id" />
                <FormField id="position_id" v-model="form.position_id" label="Poste" :options="positionOptions" placeholder="Aucun" :error="form.errors.position_id" />
                <FormField id="employment_type" v-model="form.employment_type" label="Type d'emploi" :options="options('employmentType')" :error="form.errors.employment_type" />
                <FormField id="hire_date" v-model="form.hire_date" label="Date d'embauche" type="date" :error="form.errors.hire_date" />
                <FormField id="status" v-model="form.status" label="Statut" :options="options('personnelStatus')" :error="form.errors.status" />
                <FormField id="termination_date" v-model="form.termination_date" label="Date de sortie" type="date" :error="form.errors.termination_date" />
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle>Coordonnées</CardTitle></CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-3">
                <FormField id="email" v-model="form.email" label="E-mail" type="email" :error="form.errors.email" />
                <FormField id="phone" v-model="form.phone" label="Téléphone" :error="form.errors.phone" />
                <FormField id="mobile" v-model="form.mobile" label="Mobile" :error="form.errors.mobile" />
                <FormField id="address" v-model="form.address" label="Adresse" class="sm:col-span-2" :error="form.errors.address" />
                <FormField id="city" v-model="form.city" label="Ville" :error="form.errors.city" />
                <FormField id="emergency_contact_name" v-model="form.emergency_contact_name" label="Contact d'urgence" :error="form.errors.emergency_contact_name" />
                <FormField id="emergency_contact_phone" v-model="form.emergency_contact_phone" label="Téléphone d'urgence" :error="form.errors.emergency_contact_phone" />
                <FormField id="emergency_contact_relation" v-model="form.emergency_contact_relation" label="Lien" :error="form.errors.emergency_contact_relation" />
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle>Banque</CardTitle></CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-3">
                <FormField id="bank_name" v-model="form.bank_name" label="Banque" :error="form.errors.bank_name" />
                <FormField id="bank_account" v-model="form.bank_account" label="N° de compte / RIB" :error="form.errors.bank_account" />
                <FormField id="bank_iban" v-model="form.bank_iban" label="IBAN" :error="form.errors.bank_iban" />
                <FormField id="notes" v-model="form.notes" label="Notes" type="textarea" class="sm:col-span-3" :error="form.errors.notes" />
            </CardContent>
        </Card>

        <div class="flex justify-end gap-2">
            <Button variant="outline" as-child><Link :href="cancelHref">Annuler</Link></Button>
            <Button type="submit" :disabled="form.processing">{{ submitLabel }}</Button>
        </div>
    </form>
</template>
