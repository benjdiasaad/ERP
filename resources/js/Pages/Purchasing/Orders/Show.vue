<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Ban, CircleCheck, FileText, PackageCheck, Pencil, Send, Trash2 } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import ReasonDialog from '@/components/ReasonDialog.vue';
import DocumentLinesTable from '@/components/sales/DocumentLinesTable.vue';
import StatusTimeline from '@/components/sales/StatusTimeline.vue';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription,
    AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useAction } from '@/composables/useAction';
import { usePermissions } from '@/composables/usePermissions';
import { formatDate, formatMoney, label, variant } from '@/composables/format';

const props = defineProps({
    order: { type: Object, required: true },
    paymentTerm: { type: String, default: null },
    receptions: { type: Array, required: true },
    invoices: { type: Array, required: true },
});

const { can } = usePermissions();
// Computed: workflow actions reload props on this same page instance.
const o = computed(() => props.order.data);
const status = computed(() => o.value.status);
const remaining = (field) => o.value.lines.some((l) => Number(l.quantity) - Number(l[field] ?? 0) > 0);
const hasDraftReception = computed(() => props.receptions.some((r) => r.status === 'draft'));
const canReceive = computed(() => ['confirmed', 'in_progress'].includes(status.value) && remaining('received_quantity') && !hasDraftReception.value);
const canInvoice = computed(() => ['confirmed', 'in_progress', 'received'].includes(status.value) && remaining('invoiced_quantity'));

const timeline = computed(() => [
    { label: 'Créé', date: o.value.created_at, done: true },
    { label: 'Envoyé au fournisseur', date: o.value.sent_at, done: !!o.value.sent_at },
    status.value === 'cancelled'
        ? { label: 'Annulé', date: o.value.cancelled_at, done: true, failed: true }
        : { label: 'Confirmé', date: o.value.confirmed_at, done: !!o.value.confirmed_at },
    { label: 'Reçu', date: null, done: ['received', 'invoiced'].includes(status.value) },
    { label: 'Facturé', date: null, done: props.invoices.length > 0 },
]);

const { run, processing } = useAction();
const act = (action) => run(route(`purchasing.orders.${action}`, o.value.id));
const destroy = () => router.delete(route('purchasing.orders.destroy', o.value.id));
</script>

<template>
    <AppLayout :title="o.reference" :breadcrumbs="[{ title: 'Achats' }, { title: 'Bons de commande', href: route('purchasing.orders.index') }, { title: o.reference }]">
        <PageHeader :title="o.reference" :description="`${o.supplier?.name ?? ''} · ${formatDate(o.order_date)}`">
            <template #actions>
                <Button v-if="status === 'draft' && can('purchase_orders.send')" :disabled="processing" @click="act('send')"><Send /> Envoyer</Button>
                <Button v-if="status === 'sent' && can('purchase_orders.confirm')" :disabled="processing" @click="act('confirm')"><CircleCheck /> Confirmé par le fournisseur</Button>
                <Button v-if="canReceive && can('purchase_orders.generate_reception')" :disabled="processing" @click="act('generate-reception')">
                    <PackageCheck /> Réceptionner
                </Button>
                <Button v-if="canInvoice && can('purchase_orders.generate_invoice')" variant="outline" :disabled="processing" @click="act('generate-invoice')">
                    <FileText /> Facture fournisseur
                </Button>
                <Button v-if="status === 'draft' && can('purchase_orders.update')" variant="outline" as-child>
                    <Link :href="route('purchasing.orders.edit', o.id)"><Pencil /> Modifier</Link>
                </Button>
                <ReasonDialog v-if="!['received', 'invoiced', 'cancelled'].includes(status) && can('purchase_orders.cancel')"
                    :url="route('purchasing.orders.cancel', o.id)" title="Annuler le bon de commande" :description="`${o.reference} · ${o.supplier?.name ?? ''}`"
                    confirm-label="Annuler la commande" trigger-variant="ghost">
                    <Ban /> Annuler
                </ReasonDialog>
                <AlertDialog v-if="status === 'draft' && can('purchase_orders.delete')">
                    <AlertDialogTrigger as-child>
                        <Button variant="ghost" size="icon" aria-label="Supprimer"><Trash2 /></Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Supprimer le bon {{ o.reference }} ?</AlertDialogTitle>
                            <AlertDialogDescription>{{ o.supplier?.name }} · {{ formatMoney(o.total_ttc) }}</AlertDialogDescription>
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
                <DocumentLinesTable :document="o" :show-progress="!['draft', 'sent'].includes(status)" progress-field="received_quantity" progress-label="Reçu" />

                <Card>
                    <CardHeader><CardTitle>Réceptions</CardTitle></CardHeader>
                    <CardContent>
                        <ul v-if="receptions.length" class="grid gap-3">
                            <li v-for="note in receptions" :key="note.id" class="flex items-center justify-between gap-3 text-sm">
                                <div>
                                    <Link :href="route('purchasing.receptions.show', note.id)" class="font-medium hover:underline">{{ note.reference }}</Link>
                                    <p class="text-xs text-muted-foreground">{{ formatDate(note.reception_date) }}</p>
                                </div>
                                <Badge :variant="variant('receptionStatus', note.status)">{{ label('receptionStatus', note.status) }}</Badge>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-muted-foreground">Aucune réception.</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Factures fournisseur</CardTitle></CardHeader>
                    <CardContent>
                        <ul v-if="invoices.length" class="grid gap-3">
                            <li v-for="invoice in invoices" :key="invoice.id" class="flex items-center justify-between gap-3 text-sm">
                                <div>
                                    <Link :href="route('purchasing.invoices.show', invoice.id)" class="font-medium hover:underline">{{ invoice.reference }}</Link>
                                    <p class="text-xs text-muted-foreground">{{ formatDate(invoice.invoice_date) }}</p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="tabular-nums">{{ formatMoney(invoice.total_ttc) }}</span>
                                    <Badge :variant="variant('invoiceStatus', invoice.status)">{{ label('invoiceStatus', invoice.status) }}</Badge>
                                </div>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-muted-foreground">Aucune facture.</p>
                    </CardContent>
                </Card>
            </div>

            <div class="grid content-start gap-4">
                <Card>
                    <CardHeader><CardTitle>Statut</CardTitle></CardHeader>
                    <CardContent class="grid gap-4 text-sm">
                        <div><Badge :variant="variant('purchaseOrderStatus', status)">{{ label('purchaseOrderStatus', status) }}</Badge></div>
                        <StatusTimeline :steps="timeline" />
                        <p v-if="o.cancellation_reason" class="rounded-md bg-muted p-3 whitespace-pre-line">{{ o.cancellation_reason }}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Informations</CardTitle></CardHeader>
                    <CardContent>
                        <dl class="grid gap-3 text-sm">
                            <div><dt class="text-muted-foreground">Fournisseur</dt>
                                <dd>
                                    <Link v-if="o.supplier && can('suppliers.view')" :href="route('purchasing.suppliers.show', o.supplier.id)" class="font-medium hover:underline">{{ o.supplier.name }}</Link>
                                    <span v-else class="font-medium">{{ o.supplier?.name ?? '—' }}</span>
                                </dd>
                            </div>
                            <div v-if="o.purchase_request"><dt class="text-muted-foreground">Demande d'origine</dt>
                                <dd><Link :href="route('purchasing.requests.show', o.purchase_request.id)" class="hover:underline">{{ o.purchase_request.reference }}</Link></dd>
                            </div>
                            <div><dt class="text-muted-foreground">Livraison attendue</dt><dd>{{ formatDate(o.expected_delivery_date) }}</dd></div>
                            <div><dt class="text-muted-foreground">Adresse de livraison</dt><dd class="whitespace-pre-line">{{ o.delivery_address ?? '—' }}</dd></div>
                            <div><dt class="text-muted-foreground">Conditions de paiement</dt><dd>{{ paymentTerm ?? '—' }}</dd></div>
                            <div><dt class="text-muted-foreground">Montant facturé</dt><dd class="tabular-nums">{{ formatMoney(o.amount_invoiced) }}</dd></div>
                            <div v-if="o.notes"><dt class="text-muted-foreground">Notes</dt><dd class="whitespace-pre-line">{{ o.notes }}</dd></div>
                        </dl>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
