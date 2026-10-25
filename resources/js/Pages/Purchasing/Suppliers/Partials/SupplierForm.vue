<script setup>
import { Link } from '@inertiajs/vue3';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';

defineProps({
    form: { type: Object, required: true },
    paymentTerms: { type: Array, required: true },
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
            <CardContent class="grid gap-4 sm:grid-cols-3">
                <FormField id="name" v-model="form.name" :label="form.type === 'company' ? 'Raison sociale' : 'Nom complet'" required class="sm:col-span-3" :error="form.errors.name" />
                <template v-if="form.type === 'company'">
                    <FormField id="ice" v-model="form.ice" label="ICE" :error="form.errors.ice" />
                    <FormField id="rc" v-model="form.rc" label="RC" :error="form.errors.rc" />
                    <FormField id="tax_id" v-model="form.tax_id" label="Identifiant fiscal (IF)" :error="form.errors.tax_id" />
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
            <CardHeader><CardTitle>Paiement</CardTitle></CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-3">
                <FormField id="payment_term_id" v-model="form.payment_term_id" label="Conditions de paiement" :options="paymentTerms" placeholder="Aucune" :error="form.errors.payment_term_id" />
                <FormField id="bank_name" v-model="form.bank_name" label="Banque" :error="form.errors.bank_name" />
                <FormField id="bank_account_number" v-model="form.bank_account_number" label="N° de compte / RIB" :error="form.errors.bank_account_number" />
                <FormField id="bank_iban" v-model="form.bank_iban" label="IBAN" :error="form.errors.bank_iban" />
                <FormField id="bank_swift" v-model="form.bank_swift" label="SWIFT / BIC" :error="form.errors.bank_swift" />
                <FormField id="notes" v-model="form.notes" label="Notes" type="textarea" class="sm:col-span-3" :error="form.errors.notes" />
                <div class="flex items-center gap-2">
                    <Checkbox id="is_active" v-model="form.is_active" />
                    <Label for="is_active" class="font-normal">Fournisseur actif</Label>
                </div>
            </CardContent>
        </Card>

        <div class="flex justify-end gap-2">
            <Button variant="outline" as-child><Link :href="cancelHref">Annuler</Link></Button>
            <Button type="submit" :disabled="form.processing">{{ submitLabel }}</Button>
        </div>
    </form>
</template>
