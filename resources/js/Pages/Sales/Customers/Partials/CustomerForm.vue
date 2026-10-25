<script setup>
import { Link } from '@inertiajs/vue3';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';

defineProps({
    form: { type: Object, required: true },
    paymentTerms: { type: Array, required: true },
    currencies: { type: Array, required: true },
    submitLabel: { type: String, required: true },
    cancelHref: { type: String, required: true },
});

defineEmits(['submit']);
</script>

<template>
    <form class="grid gap-6" @submit.prevent="$emit('submit')">
        <Card>
            <CardHeader>
                <CardTitle>Identité</CardTitle>
                <CardAction>
                    <Tabs v-model="form.type">
                        <TabsList>
                            <TabsTrigger value="company">Entreprise</TabsTrigger>
                            <TabsTrigger value="individual">Particulier</TabsTrigger>
                        </TabsList>
                    </Tabs>
                </CardAction>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2">
                <template v-if="form.type === 'company'">
                    <FormField id="name" v-model="form.name" label="Raison sociale" required class="sm:col-span-2" :error="form.errors.name" />
                    <FormField id="ice" v-model="form.ice" label="ICE" :error="form.errors.ice" />
                    <FormField id="rc" v-model="form.rc" label="RC" :error="form.errors.rc" />
                    <FormField id="tax_id" v-model="form.tax_id" label="Identifiant fiscal (IF)" :error="form.errors.tax_id" />
                </template>
                <template v-else>
                    <FormField id="first_name" v-model="form.first_name" label="Prénom" required :error="form.errors.first_name" />
                    <FormField id="last_name" v-model="form.last_name" label="Nom" required :error="form.errors.last_name" />
                </template>
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle>Coordonnées</CardTitle></CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-3">
                <FormField id="email" v-model="form.email" label="E-mail" type="email" :error="form.errors.email" />
                <FormField id="phone" v-model="form.phone" label="Téléphone" :error="form.errors.phone" />
                <FormField id="mobile" v-model="form.mobile" label="Mobile" :error="form.errors.mobile" />
                <FormField id="address" v-model="form.address" label="Adresse" class="sm:col-span-3" :error="form.errors.address" />
                <FormField id="postal_code" v-model="form.postal_code" label="Code postal" :error="form.errors.postal_code" />
                <FormField id="city" v-model="form.city" label="Ville" :error="form.errors.city" />
                <FormField id="country" v-model="form.country" label="Pays" :error="form.errors.country" />
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Conditions commerciales</CardTitle>
                <CardDescription>Appliquées par défaut aux devis et factures de ce client.</CardDescription>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-3">
                <FormField id="payment_terms_id" v-model="form.payment_terms_id" label="Conditions de paiement" :options="paymentTerms" placeholder="Aucune" :error="form.errors.payment_terms_id" />
                <FormField id="currency_id" v-model="form.currency_id" label="Devise" :options="currencies" placeholder="Par défaut" :error="form.errors.currency_id" />
                <FormField id="credit_limit" v-model="form.credit_limit" label="Plafond de crédit (MAD)" type="number" :error="form.errors.credit_limit" />
                <FormField id="notes" v-model="form.notes" label="Notes" type="textarea" class="sm:col-span-3" :error="form.errors.notes" />
                <div class="flex items-center gap-2">
                    <Checkbox id="is_active" v-model="form.is_active" />
                    <Label for="is_active" class="font-normal">Client actif</Label>
                </div>
            </CardContent>
        </Card>

        <div class="flex justify-end gap-2">
            <Button variant="outline" as-child><Link :href="cancelHref">Annuler</Link></Button>
            <Button type="submit" :disabled="form.processing">{{ submitLabel }}</Button>
        </div>
    </form>
</template>
