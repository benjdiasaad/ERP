<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Ban, CircleCheck, Pencil, Trash2 } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import ReasonDialog from '@/components/ReasonDialog.vue';
import DocumentLinesTable from '@/components/sales/DocumentLinesTable.vue';
import PaymentDialog from '@/components/sales/PaymentDialog.vue';
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
});

const { can } = usePermissions();
// Computed: workflow actions reload props on this same page instance.
const i = computed(() => props.invoice.data);
const status = computed(() => i.value.status);
const payable = computed(() => ['sent', 'partial', 'overdue'].includes(status.value));
const paidPercent = computed(() => (Number(i.value.total_ttc) > 0 ? (Number(i.value.amount_paid) / Number(i.value.total_ttc)) * 100 : 0));

const { run, processing } = useAction();
const validate = () => run(route('purchasing.invoices.send', i.value.id));
const destroy = () => router.delete(route('purchasing.invoices.destroy', i.value.id));
</script>

<template>
    <AppLayout :title="i.reference" :breadcrumbs="[{ title: 'Achats' }, { title: 'Factures fournisseurs', href: route('purchasing.invoices.index') }, { title: i.reference }]">
        <PageHeader :title="i.reference" :description="`${i.supplier?.name ?? ''} · ${formatDate(i.invoice_date)}`">
            <template #actions>
                <Button v-if="status === 'draft' && can('purchase_invoices.send')" :disabled="processing" @click="validate"><CircleCheck /> Valider</Button>
                <PaymentDialog v-if="payable && can('purchase_invoices.record_payment')" :invoice="i" :url="route('purchasing.invoices.record-payment', i.id)" />
                <Button v-if="status === 'draft' && can('purchase_invoices.update')" variant="outline" as-child>
                    <Link :href="route('purchasing.invoices.edit', i.id)"><Pencil /> Modifier</Link>
                </Button>
                <ReasonDialog v-if="!['paid', 'cancelled'].includes(status) && can('purchase_invoices.cancel')"
                    :url="route('purchasing.invoices.cancel', i.id)" title="Annuler la facture" :description="`${i.reference} · ${formatMoney(i.total_ttc)}`"
                    confirm-label="Annuler la facture" trigger-variant="ghost">
                    <Ban /> Annuler
                </ReasonDialog>
                <AlertDialog v-if="status === 'draft' && can('purchase_invoices.delete')">
                    <AlertDialogTrigger as-child>
                        <Button variant="ghost" size="icon" aria-label="Supprimer"><Trash2 /></Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Supprimer la facture {{ i.reference }} ?</AlertDialogTitle>
                            <AlertDialogDescription>{{ i.supplier?.name }} · {{ formatMoney(i.total_ttc) }}</AlertDialogDescription>
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
                        <div class="flex justify-between font-semibold" :class="i.is_overdue ? 'text-destructive' : ''">
                            <dt>Reste à payer</dt><dd class="tabular-nums">{{ formatMoney(i.amount_due) }}</dd>
                        </div>
                    </template>
                </DocumentLinesTable>
            </div>

            <div class="grid content-start gap-4">
                <Card>
                    <CardHeader><CardTitle>Règlement</CardTitle></CardHeader>
                    <CardContent class="grid gap-3 text-sm">
                        <div><Badge :variant="variant('invoiceStatus', status)">{{ label('invoiceStatus', status) }}</Badge></div>
                        <p class="text-2xl font-semibold tabular-nums">{{ formatMoney(i.amount_paid) }}
                            <span class="text-sm font-normal text-muted-foreground">/ {{ formatMoney(i.total_ttc) }}</span>
                        </p>
                        <Progress :model-value="paidPercent" />
                        <p :class="i.is_overdue ? 'font-medium text-destructive' : 'text-muted-foreground'">
                            Échéance {{ formatDate(i.due_date) }}<template v-if="i.is_overdue"> — dépassée</template>
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Informations</CardTitle></CardHeader>
                    <CardContent>
                        <dl class="grid gap-3 text-sm">
                            <div><dt class="text-muted-foreground">Fournisseur</dt>
                                <dd>
                                    <Link v-if="i.supplier && can('suppliers.view')" :href="route('purchasing.suppliers.show', i.supplier.id)" class="font-medium hover:underline">{{ i.supplier.name }}</Link>
                                    <span v-else class="font-medium">{{ i.supplier?.name ?? '—' }}</span>
                                </dd>
                            </div>
                            <div v-if="i.purchase_order"><dt class="text-muted-foreground">Bon de commande</dt>
                                <dd><Link :href="route('purchasing.orders.show', i.purchase_order.id)" class="hover:underline">{{ i.purchase_order.reference }}</Link></dd>
                            </div>
                            <div><dt class="text-muted-foreground">Conditions de paiement</dt><dd>{{ i.payment_term?.name ?? '—' }}</dd></div>
                            <div v-if="i.notes"><dt class="text-muted-foreground">Notes</dt><dd class="whitespace-pre-line">{{ i.notes }}</dd></div>
                        </dl>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
