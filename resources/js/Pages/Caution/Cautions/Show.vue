<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { Ban, CalendarPlus, CircleCheck, Gavel, Pencil, Play, Trash2, Undo2 } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import FormField from '@/components/FormField.vue';
import ReasonDialog from '@/components/ReasonDialog.vue';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription,
    AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Progress } from '@/components/ui/progress';
import { Separator } from '@/components/ui/separator';
import { useAction } from '@/composables/useAction';
import { usePermissions } from '@/composables/usePermissions';
import { formatAmount, formatDate, formatDateTime, label, variant } from '@/composables/format';

const props = defineProps({
    caution: { type: Object, required: true },
});

const { can } = usePermissions();
// Computed: workflow actions reload props on this same page instance.
const c = computed(() => props.caution.data);
const held = computed(() => ['active', 'partially_returned'].includes(c.value.status));
const returnedShare = computed(() => (Number(c.value.amount) ? Math.round((Number(c.value.amount_returned) / Number(c.value.amount)) * 100) : 0));
const partnerRoute = computed(() => ({
    customer: c.value.partner && can('customers.view') ? route('sales.customers.show', c.value.partner.id) : null,
    supplier: c.value.partner && can('suppliers.view') ? route('purchasing.suppliers.show', c.value.partner.id) : null,
}[c.value.partner_alias] ?? null));

const { run, processing } = useAction();
const activate = () => run(route('finance.cautions.activate', c.value.id));
const destroy = () => router.delete(route('finance.cautions.destroy', c.value.id));

// Partial return: an amount up to what is still held.
const returnOpen = ref(false);
const returnForm = useForm({ amount: null, notes: '' });
const openReturn = () => { returnForm.reset(); returnForm.clearErrors(); returnForm.amount = Number(c.value.remaining); };
const submitReturn = () => returnForm.post(route('finance.cautions.partial-return', c.value.id), {
    preserveScroll: true, onSuccess: () => { returnOpen.value = false; },
});

// Extension: a later expiry date.
const extendOpen = ref(false);
const extendForm = useForm({ expiry_date: null, notes: '' });
const openExtend = () => { extendForm.reset(); extendForm.clearErrors(); };
const submitExtend = () => extendForm.post(route('finance.cautions.extend', c.value.id), {
    preserveScroll: true, onSuccess: () => { extendOpen.value = false; },
});
</script>

<template>
    <AppLayout :title="c.caution_type?.name ?? 'Caution'" :breadcrumbs="[{ title: 'Finance' }, { title: 'Cautions', href: route('finance.cautions.index') }, { title: c.document_reference ?? `Caution #${c.id}` }]">
        <PageHeader :title="`${c.caution_type?.name ?? 'Caution'} ${label('cautionDirection', c.direction).toLowerCase()}`"
            :description="[c.partner?.name, c.document_reference].filter(Boolean).join(' · ')">
            <template #actions>
                <Badge :variant="variant('cautionStatus', c.status)">{{ label('cautionStatus', c.status) }}</Badge>

                <Button v-if="c.status === 'draft' && can('cautions.activate')" :disabled="processing" @click="activate"><Play /> Activer</Button>

                <Dialog v-if="held && can('cautions.partial_return')" v-model:open="returnOpen" @update:open="(value) => value && openReturn()">
                    <DialogTrigger as-child><Button><Undo2 /> Restitution</Button></DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Enregistrer une restitution</DialogTitle>
                            <DialogDescription>Reste à restituer : {{ formatAmount(c.remaining, c.currency) }}. Restituer la totalité clôt la caution.</DialogDescription>
                        </DialogHeader>
                        <form class="grid gap-4" @submit.prevent="submitReturn">
                            <FormField id="return-amount" v-model="returnForm.amount" label="Montant restitué" type="number" required :error="returnForm.errors.amount" />
                            <FormField id="return-notes" v-model="returnForm.notes" label="Notes" type="textarea" :error="returnForm.errors.notes" />
                            <p v-if="returnForm.errors.status" class="text-sm text-destructive">{{ returnForm.errors.status }}</p>
                            <DialogFooter><Button type="submit" :disabled="returnForm.processing">Enregistrer</Button></DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                <ReasonDialog v-if="held && can('cautions.full_return')" :url="route('finance.cautions.full-return', c.id)" field="notes" field-label="Notes (optionnel)"
                    title="Restitution totale" :description="`Le solde de ${formatAmount(c.remaining, c.currency)} est restitué et la caution est clôturée.`"
                    confirm-label="Confirmer la restitution" :destructive="false">
                    <CircleCheck /> Restituée
                </ReasonDialog>

                <Dialog v-if="held && can('cautions.extend')" v-model:open="extendOpen" @update:open="(value) => value && openExtend()">
                    <DialogTrigger as-child><Button variant="outline"><CalendarPlus /> Prolonger</Button></DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Prolonger l'échéance</DialogTitle>
                            <DialogDescription>Échéance actuelle : {{ formatDate(c.expiry_date) }}.</DialogDescription>
                        </DialogHeader>
                        <form class="grid gap-4" @submit.prevent="submitExtend">
                            <FormField id="extend-date" v-model="extendForm.expiry_date" label="Nouvelle échéance" type="date" required :error="extendForm.errors.expiry_date" />
                            <FormField id="extend-notes" v-model="extendForm.notes" label="Notes" type="textarea" :error="extendForm.errors.notes" />
                            <DialogFooter><Button type="submit" :disabled="extendForm.processing">Prolonger</Button></DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                <ReasonDialog v-if="held && can('cautions.forfeit')" :url="route('finance.cautions.forfeit', c.id)" field="notes" field-label="Motif"
                    title="Saisir la caution" :description="`Le solde de ${formatAmount(c.remaining, c.currency)} est définitivement perdu (saisi par le bénéficiaire).`"
                    confirm-label="Saisir la caution" trigger-variant="ghost">
                    <Gavel /> Saisie
                </ReasonDialog>

                <Button v-if="c.status === 'draft' && can('cautions.update')" variant="outline" as-child>
                    <Link :href="route('finance.cautions.edit', c.id)"><Pencil /> Modifier</Link>
                </Button>
                <ReasonDialog v-if="c.status === 'draft' && can('cautions.cancel')" :url="route('finance.cautions.cancel', c.id)" field="notes"
                    title="Annuler la caution" confirm-label="Annuler la caution" trigger-variant="ghost">
                    <Ban /> Annuler
                </ReasonDialog>
                <AlertDialog v-if="['draft', 'cancelled'].includes(c.status) && can('cautions.delete')">
                    <AlertDialogTrigger as-child>
                        <Button variant="ghost" size="icon" aria-label="Supprimer"><Trash2 /></Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Supprimer cette caution ?</AlertDialogTitle>
                            <AlertDialogDescription>La caution et son historique seront retirés des listes.</AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Annuler</AlertDialogCancel>
                            <AlertDialogAction class="bg-destructive text-white hover:bg-destructive/90" @click="destroy">Supprimer</AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </template>
        </PageHeader>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="grid content-start gap-4 lg:col-span-2">
                <Card>
                    <CardHeader><CardTitle>Montants</CardTitle></CardHeader>
                    <CardContent class="grid gap-4">
                        <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
                            <div><p class="text-sm text-muted-foreground">Montant</p><p class="text-lg font-semibold whitespace-nowrap tabular-nums">{{ formatAmount(c.amount, c.currency) }}</p></div>
                            <div><p class="text-sm text-muted-foreground">Restitué</p><p class="text-lg whitespace-nowrap tabular-nums">{{ formatAmount(c.amount_returned, c.currency) }}</p></div>
                            <div><p class="text-sm text-muted-foreground">Saisi</p><p class="text-lg whitespace-nowrap tabular-nums" :class="Number(c.amount_forfeited) ? 'text-destructive' : ''">{{ formatAmount(c.amount_forfeited, c.currency) }}</p></div>
                            <div><p class="text-sm text-muted-foreground">Reste</p><p class="text-lg whitespace-nowrap tabular-nums">{{ formatAmount(c.remaining, c.currency) }}</p></div>
                        </div>
                        <Progress :model-value="returnedShare" :aria-label="`${returnedShare} % restitué`" />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Historique</CardTitle></CardHeader>
                    <CardContent>
                        <ol class="grid gap-4">
                            <li v-for="h in c.histories" :key="h.id" class="grid gap-0.5 border-l-2 pl-4 text-sm">
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <p class="font-medium">{{ label('cautionAction', h.action) }}
                                        <span v-if="['partial_return', 'full_return', 'forfeited'].includes(h.action)" class="font-normal tabular-nums text-muted-foreground"> · {{ formatAmount(h.amount, c.currency) }}</span>
                                    </p>
                                    <p class="text-xs text-muted-foreground">{{ formatDateTime(h.created_at) }}<template v-if="h.author"> · {{ h.author }}</template></p>
                                </div>
                                <p v-if="h.notes" class="whitespace-pre-line text-muted-foreground">{{ h.notes }}</p>
                            </li>
                        </ol>
                    </CardContent>
                </Card>
            </div>

            <Card class="self-start">
                <CardHeader><CardTitle>Informations</CardTitle></CardHeader>
                <CardContent>
                    <dl class="grid gap-3 text-sm">
                        <div><dt class="text-muted-foreground">{{ label('cautionPartner', c.partner_alias) }}</dt>
                            <dd>
                                <Link v-if="partnerRoute" :href="partnerRoute" class="font-medium hover:underline">{{ c.partner.name }}</Link>
                                <span v-else class="font-medium">{{ c.partner?.name ?? '—' }}</span>
                            </dd>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div><dt class="text-muted-foreground">Émise le</dt><dd>{{ formatDate(c.issue_date) }}</dd></div>
                            <div><dt class="text-muted-foreground">Échéance</dt><dd>{{ formatDate(c.expiry_date) }}</dd></div>
                        </div>
                        <div v-if="c.return_date"><dt class="text-muted-foreground">Restituée le</dt><dd>{{ formatDate(c.return_date) }}</dd></div>
                        <Separator />
                        <div><dt class="text-muted-foreground">Banque</dt><dd>{{ c.bank_name ?? '—' }}</dd></div>
                        <div v-if="c.bank_account"><dt class="text-muted-foreground">Compte</dt><dd class="break-all">{{ c.bank_account }}</dd></div>
                        <div v-if="c.bank_reference"><dt class="text-muted-foreground">N° de caution bancaire</dt><dd class="break-all">{{ c.bank_reference }}</dd></div>
                        <div v-if="c.notes"><dt class="text-muted-foreground">Notes</dt><dd class="whitespace-pre-line">{{ c.notes }}</dd></div>
                    </dl>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
