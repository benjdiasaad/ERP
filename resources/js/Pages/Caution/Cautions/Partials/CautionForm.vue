<script setup>
import { computed, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { labels } from '@/composables/format';

/**
 * `lockedParty`: direction and partner cannot change once the caution exists.
 */
const props = defineProps({
    form: { type: Object, required: true },
    types: { type: Array, required: true },
    customers: { type: Array, required: true },
    suppliers: { type: Array, required: true },
    lockedParty: { type: Boolean, default: false },
    partnerLabel: { type: String, default: null },
    submitLabel: { type: String, required: true },
    cancelHref: { type: String, required: true },
});

defineEmits(['submit']);

const partnerTypeOptions = Object.entries(labels.cautionPartner).map(([value, label]) => ({ value, label }));
const partners = computed(() => ({ customer: props.customers, supplier: props.suppliers }[props.form.partner_type] ?? []));
watch(() => props.form.partner_type, () => {
    if (!props.lockedParty) props.form.partner_id = null;
});
</script>

<template>
    <form class="grid gap-6" @submit.prevent="$emit('submit')">
        <Card>
            <CardHeader>
                <CardTitle>Caution</CardTitle>
                <CardDescription>
                    {{ form.direction === 'given' ? 'Garantie déposée par la société (ex. : caution de soumission à un appel d\'offres).' : 'Garantie reçue d\'un partenaire (ex. : caution de bonne exécution d\'un fournisseur).' }}
                </CardDescription>
                <CardAction v-if="!lockedParty">
                    <Tabs v-model="form.direction">
                        <TabsList>
                            <TabsTrigger value="given">Donnée</TabsTrigger>
                            <TabsTrigger value="received">Reçue</TabsTrigger>
                        </TabsList>
                    </Tabs>
                </CardAction>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-3">
                <FormField id="caution_type_id" v-model="form.caution_type_id" label="Type" :options="types" placeholder="Choisir un type…" required :error="form.errors.caution_type_id" />
                <template v-if="!lockedParty">
                    <FormField id="partner_type" v-model="form.partner_type" label="Partenaire" :options="partnerTypeOptions" :error="form.errors.partner_type" />
                    <FormField v-if="form.partner_type !== 'other'" id="partner_id" v-model="form.partner_id"
                        :label="form.partner_type === 'supplier' ? 'Fournisseur' : 'Client'" :options="partners" placeholder="Choisir…" required :error="form.errors.partner_id" />
                </template>
                <div v-else class="grid gap-2 sm:col-span-2">
                    <p class="text-sm font-medium">Partenaire</p>
                    <p class="text-sm text-muted-foreground">{{ partnerLabel ?? 'Autre' }}</p>
                </div>
                <FormField id="amount" v-model="form.amount" label="Montant" type="number" required :error="form.errors.amount" />
                <FormField id="issue_date" v-model="form.issue_date" label="Date d'émission" type="date" required :error="form.errors.issue_date" />
                <FormField id="expiry_date" v-model="form.expiry_date" label="Date d'échéance" type="date" required :error="form.errors.expiry_date" />
                <FormField id="document_reference" v-model="form.document_reference" label="Référence du marché / document" class="sm:col-span-3" :error="form.errors.document_reference" />
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle>Banque</CardTitle></CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-3">
                <FormField id="bank_name" v-model="form.bank_name" label="Banque émettrice" :error="form.errors.bank_name" />
                <FormField id="bank_account" v-model="form.bank_account" label="Compte" :error="form.errors.bank_account" />
                <FormField id="bank_reference" v-model="form.bank_reference" label="N° de caution bancaire" :error="form.errors.bank_reference" />
                <FormField id="notes" v-model="form.notes" label="Notes" type="textarea" class="sm:col-span-3" :error="form.errors.notes" />
            </CardContent>
        </Card>

        <div class="flex justify-end gap-2">
            <Button variant="outline" as-child><Link :href="cancelHref">Annuler</Link></Button>
            <Button type="submit" :disabled="form.processing">{{ submitLabel }}</Button>
        </div>
    </form>
</template>
