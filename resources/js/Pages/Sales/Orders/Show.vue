<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Ban, CircleCheck, FileText, Pencil, Trash2, Truck } from '@lucide/vue';
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
    quote: { type: Object, default: null },
    invoices: { type: Array, required: true },
    deliveryNotes: { type: Array, required: true },
});

const { can } = usePermissions();
// Computed: workflow actions reload props on this same page instance.
const o = computed(() => props.order.data);
const status = computed(() => o.value.status);
const canDeliver = computed(() => ['confirmed', 'in_progress'].includes(status.value)
    && o.value.lines.some((l) => Number(l.remaining_to_deliver) > 0));
const canInvoice = computed(() => ['confirmed', 'in_progress', 'delivered'].includes(status.value)
    && o.value.lines.some((l) => Number(l.remaining_to_invoice) > 0));

const timeline = computed(() => [
    { label: 'Créée', date: o.value.created_at, done: true },
    status.value === 'cancelled'
        ? { label: 'Annulée', date: o.value.cancelled_at, done: true, failed: true }
        : { label: 'Confirmée', date: o.value.confirmed_at, done: !!o.value.confirmed_at },
    { label: 'Livraison', date: null, done: props.deliveryNotes.length > 0 },
    { label: 'Facturation', date: null, done: props.invoices.length > 0 },
]);

const { run, processing } = useAction();
const act = (action) => run(route(`sales.orders.${action}`, o.value.id));
const destroy = () => router.delete(route('sales.orders.destroy', o.value.id));
</script>

<template>
    <AppLayout :title="o.reference" :breadcrumbs="[{ title: 'Ventes' }, { title: 'Commandes', href: route('sales.orders.index') }, { title: o.reference }]">
        <PageHeader :title="o.reference" :description="`${o.customer?.name ?? ''} · ${formatDate(o.order_date)}`">
            <template #actions>
                <Button v-if="status === 'draft' && can('sales_orders.confirm')" :disabled="processing" @click="act('confirm')"><CircleCheck /> Confirmer</Button>
                <Button v-if="canDeliver && can('sales_orders.generate_delivery_note')" variant="outline" :disabled="processing" @click="act('generate-delivery-note')">
                    <Truck /> Bon de livraison
                </Button>
                <Button v-if="canInvoice && can('sales_orders.generate_invoice')" :disabled="processing" @click="act('generate-invoice')">
                    <FileText /> Facturer
                </Button>
                <Button v-if="status === 'draft' && can('sales_orders.update')" variant="outline" as-child>
                    <Link :href="route('sales.orders.edit', o.id)"><Pencil /> Modifier</Link>
                </Button>
                <ReasonDialog v-if="!['delivered', 'invoiced', 'cancelled'].includes(status) && can('sales_orders.cancel')"
                    :url="route('sales.orders.cancel', o.id)" title="Annuler la commande" :description="`${o.reference} · ${o.customer?.name ?? ''}`"
                    confirm-label="Annuler la commande" trigger-variant="ghost">
                    <Ban /> Annuler
                </ReasonDialog>
                <AlertDialog v-if="status === 'draft' && can('sales_orders.delete')">
                    <AlertDialogTrigger as-child>
                        <Button variant="ghost" size="icon" aria-label="Supprimer"><Trash2 /></Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Supprimer la commande {{ o.reference }} ?</AlertDialogTitle>
                            <AlertDialogDescription>{{ o.customer?.name }} · {{ formatMoney(o.total_ttc) }}</AlertDialogDescription>
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
                <DocumentLinesTable :document="o" :show-progress="status !== 'draft'" />

                <Card>
                    <CardHeader><CardTitle>Factures</CardTitle></CardHeader>
                    <CardContent>
                        <ul v-if="invoices.length" class="grid gap-3">
                            <li v-for="invoice in invoices" :key="invoice.id" class="flex items-center justify-between gap-3 text-sm">
                                <div>
                                    <Link :href="route('sales.invoices.show', invoice.id)" class="font-medium hover:underline">{{ invoice.reference }}</Link>
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

                <Card>
                    <CardHeader><CardTitle>Bons de livraison</CardTitle></CardHeader>
                    <CardContent>
                        <ul v-if="deliveryNotes.length" class="grid gap-3">
                            <li v-for="note in deliveryNotes" :key="note.id" class="flex items-center justify-between gap-3 text-sm">
                                <div>
                                    <Link :href="route('sales.delivery-notes.show', note.id)" class="font-medium hover:underline">{{ note.reference }}</Link>
                                    <p class="text-xs text-muted-foreground">{{ formatDate(note.date) }}</p>
                                </div>
                                <Badge :variant="variant('deliveryStatus', note.status)">{{ label('deliveryStatus', note.status) }}</Badge>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-muted-foreground">Aucun bon de livraison.</p>
                    </CardContent>
                </Card>
            </div>

            <div class="grid content-start gap-4">
                <Card>
                    <CardHeader><CardTitle>Statut</CardTitle></CardHeader>
                    <CardContent class="grid gap-4 text-sm">
                        <div><Badge :variant="variant('orderStatus', status)">{{ label('orderStatus', status) }}</Badge></div>
                        <StatusTimeline :steps="timeline" />
                        <p v-if="o.cancellation_reason" class="rounded-md bg-muted p-3 whitespace-pre-line">{{ o.cancellation_reason }}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Informations</CardTitle></CardHeader>
                    <CardContent>
                        <dl class="grid gap-3 text-sm">
                            <div><dt class="text-muted-foreground">Client</dt>
                                <dd>
                                    <Link v-if="o.customer && can('customers.view')" :href="route('sales.customers.show', o.customer.id)" class="font-medium hover:underline">{{ o.customer.name }}</Link>
                                    <span v-else class="font-medium">{{ o.customer?.name ?? '—' }}</span>
                                </dd>
                            </div>
                            <div v-if="quote"><dt class="text-muted-foreground">Issue du devis</dt>
                                <dd><Link :href="route('sales.quotes.show', quote.id)" class="hover:underline">{{ quote.reference }}</Link></dd>
                            </div>
                            <div><dt class="text-muted-foreground">Livraison prévue</dt><dd>{{ formatDate(o.expected_delivery_date) }}</dd></div>
                            <div><dt class="text-muted-foreground">Adresse de livraison</dt><dd class="whitespace-pre-line">{{ o.delivery_address ?? '—' }}</dd></div>
                            <div><dt class="text-muted-foreground">Conditions de paiement</dt><dd>{{ o.payment_term?.name ?? '—' }}</dd></div>
                            <div><dt class="text-muted-foreground">Montant facturé</dt><dd class="tabular-nums">{{ formatMoney(o.amount_invoiced) }}</dd></div>
                            <div v-if="o.notes"><dt class="text-muted-foreground">Notes</dt><dd class="whitespace-pre-line">{{ o.notes }}</dd></div>
                        </dl>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
