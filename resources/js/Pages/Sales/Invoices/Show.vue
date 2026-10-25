<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Ban, FileMinus, Pencil, Send, Trash2 } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import ReasonDialog from '@/components/ReasonDialog.vue';
import DocumentLinesTable from '@/components/sales/DocumentLinesTable.vue';
import PaymentDialog from './Partials/PaymentDialog.vue';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription,
    AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { useAction } from '@/composables/useAction';
import { usePermissions } from '@/composables/usePermissions';
import { formatDate, formatMoney, label, variant } from '@/composables/format';

const props = defineProps({
    invoice: { type: Object, required: true },
    creditNotes: { type: Array, required: true },
});

const { can } = usePermissions();
// Computed: workflow actions reload props on this same page instance.
const i = computed(() => props.invoice.data);
const status = computed(() => i.value.status);
const payable = computed(() => ['sent', 'partial', 'overdue'].includes(status.value));
const paidPercent = computed(() => (Number(i.value.total_ttc) > 0 ? (Number(i.value.amount_paid) / Number(i.value.total_ttc)) * 100 : 0));

const { run, processing } = useAction();
const send = () => run(route('sales.invoices.send', i.value.id));
const destroy = () => router.delete(route('sales.invoices.destroy', i.value.id));
</script>

<template>
    <AppLayout :title="i.reference" :breadcrumbs="[{ title: 'Ventes' }, { title: 'Factures', href: route('sales.invoices.index') }, { title: i.reference }]">
        <PageHeader :title="i.reference" :description="`${i.customer?.name ?? ''} · ${formatDate(i.invoice_date)}`">
            <template #actions>
                <Button v-if="status === 'draft' && can('invoices.send')" :disabled="processing" @click="send"><Send /> Marquer envoyée</Button>
                <PaymentDialog v-if="payable && can('invoices.record_payment')" :invoice="i" />
                <ReasonDialog v-if="['sent', 'partial', 'paid', 'overdue'].includes(status) && can('invoices.create_credit_note')"
                    :url="route('sales.invoices.create-credit-note', i.id)" title="Créer un avoir"
                    description="Un avoir en brouillon reprenant toutes les lignes de la facture sera créé."
                    confirm-label="Créer l'avoir" :destructive="false" placeholder="Ex. : retour de marchandise, erreur de prix">
                    <FileMinus /> Avoir
                </ReasonDialog>
                <Button v-if="status === 'draft' && can('invoices.update')" variant="outline" as-child>
                    <Link :href="route('sales.invoices.edit', i.id)"><Pencil /> Modifier</Link>
                </Button>
                <ReasonDialog v-if="!['paid', 'cancelled'].includes(status) && can('invoices.cancel')"
                    :url="route('sales.invoices.cancel', i.id)" title="Annuler la facture" :description="`${i.reference} · ${formatMoney(i.total_ttc)}`"
                    confirm-label="Annuler la facture" trigger-variant="ghost">
                    <Ban /> Annuler
                </ReasonDialog>
                <AlertDialog v-if="status === 'draft' && can('invoices.delete')">
                    <AlertDialogTrigger as-child>
                        <Button variant="ghost" size="icon" aria-label="Supprimer"><Trash2 /></Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Supprimer la facture {{ i.reference }} ?</AlertDialogTitle>
                            <AlertDialogDescription>{{ i.customer?.name }} · {{ formatMoney(i.total_ttc) }}</AlertDialogDescription>
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
            <div class="grid min-w-0 content-start gap-4 lg:col-span-2">
                <DocumentLinesTable :document="i">
                    <template #after-totals>
                        <div class="flex justify-between text-muted-foreground"><dt>Déjà payé</dt><dd class="tabular-nums">{{ formatMoney(i.amount_paid) }}</dd></div>
                        <div class="flex justify-between font-semibold" :class="status === 'overdue' ? 'text-destructive' : ''">
                            <dt>Reste dû</dt><dd class="tabular-nums">{{ formatMoney(i.amount_due) }}</dd>
                        </div>
                    </template>
                </DocumentLinesTable>

                <Card v-if="creditNotes.length">
                    <CardHeader><CardTitle>Avoirs</CardTitle></CardHeader>
                    <CardContent>
                        <ul class="grid gap-3">
                            <li v-for="note in creditNotes" :key="note.id" class="flex items-center justify-between gap-3 text-sm">
                                <span class="font-medium">{{ note.reference }}</span>
                                <div class="flex items-center gap-3">
                                    <span class="tabular-nums">{{ formatMoney(note.total_ttc) }}</span>
                                    <Badge variant="secondary">{{ note.status === 'draft' ? 'Brouillon' : note.status }}</Badge>
                                </div>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>

            <div class="grid content-start gap-4">
                <Card>
                    <CardHeader><CardTitle>Paiement</CardTitle></CardHeader>
                    <CardContent class="grid gap-3 text-sm">
                        <div><Badge :variant="variant('invoiceStatus', status)">{{ label('invoiceStatus', status) }}</Badge></div>
                        <p class="text-2xl font-semibold tabular-nums">{{ formatMoney(i.amount_paid) }}
                            <span class="text-sm font-normal text-muted-foreground">/ {{ formatMoney(i.total_ttc) }}</span>
                        </p>
                        <Progress :model-value="paidPercent" />
                        <p class="text-muted-foreground" :class="status === 'overdue' ? 'font-medium text-destructive' : ''">
                            Échéance {{ formatDate(i.due_date) }}<template v-if="status === 'overdue'"> — dépassée</template>
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Informations</CardTitle></CardHeader>
                    <CardContent>
                        <dl class="grid gap-3 text-sm">
                            <div><dt class="text-muted-foreground">Client</dt>
                                <dd>
                                    <Link v-if="i.customer && can('customers.view')" :href="route('sales.customers.show', i.customer.id)" class="font-medium hover:underline">{{ i.customer.name }}</Link>
                                    <span v-else class="font-medium">{{ i.customer?.name ?? '—' }}</span>
                                </dd>
                            </div>
                            <div v-if="i.sales_order"><dt class="text-muted-foreground">Commande</dt>
                                <dd><Link :href="route('sales.orders.show', i.sales_order.id)" class="hover:underline">{{ i.sales_order.reference }}</Link></dd>
                            </div>
                            <div><dt class="text-muted-foreground">Date</dt><dd>{{ formatDate(i.invoice_date) }}</dd></div>
                            <div><dt class="text-muted-foreground">Conditions de paiement</dt><dd>{{ i.payment_term?.name ?? '—' }}</dd></div>
                            <div v-if="i.notes"><dt class="text-muted-foreground">Notes</dt><dd class="whitespace-pre-line">{{ i.notes }}</dd></div>
                        </dl>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
