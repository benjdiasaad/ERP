<script setup>
import { Link } from '@inertiajs/vue3';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

defineProps({
    form: { type: Object, required: true },
    currencies: { type: Array, required: true },
    withBalance: { type: Boolean, default: false },
    submitLabel: { type: String, required: true },
    cancelHref: { type: String, required: true },
});

defineEmits(['submit']);
</script>

<template>
    <form class="grid gap-6" @submit.prevent="$emit('submit')">
        <Card>
            <CardHeader><CardTitle>Compte</CardTitle></CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-3">
                <FormField id="name" v-model="form.name" label="Nom du compte" placeholder="Ex. : Compte courant principal" required class="sm:col-span-2" :error="form.errors.name" />
                <FormField id="currency_id" v-model="form.currency_id" label="Devise" :options="currencies" required :error="form.errors.currency_id" />
                <FormField id="bank" v-model="form.bank" label="Banque" placeholder="Ex. : Attijariwafa bank" required :error="form.errors.bank" />
                <FormField id="account_number" v-model="form.account_number" label="N° de compte / RIB" required class="sm:col-span-2" :error="form.errors.account_number" />
                <FormField id="iban" v-model="form.iban" label="IBAN" class="sm:col-span-2" :error="form.errors.iban" />
                <FormField id="swift" v-model="form.swift" label="SWIFT / BIC" :error="form.errors.swift" />
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Solde</CardTitle>
                <CardDescription v-if="withBalance">Solde à l'ouverture du compte dans l'ERP. Il évolue ensuite avec les paiements.</CardDescription>
                <CardDescription v-else>Le solde évolue avec les paiements et n'est pas modifiable ici.</CardDescription>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-3">
                <FormField v-if="withBalance" id="balance" v-model="form.balance" label="Solde initial" type="number" :error="form.errors.balance" />
                <div class="flex items-center gap-2 sm:col-span-2" :class="withBalance ? 'sm:pt-6' : ''">
                    <Checkbox id="is_active" v-model="form.is_active" />
                    <Label for="is_active" class="font-normal">Compte actif</Label>
                </div>
            </CardContent>
        </Card>

        <div class="flex justify-end gap-2">
            <Button variant="outline" as-child><Link :href="cancelHref">Annuler</Link></Button>
            <Button type="submit" :disabled="form.processing">{{ submitLabel }}</Button>
        </div>
    </form>
</template>
