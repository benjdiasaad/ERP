<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Check, Copy, PackageCheck, Pencil, Send, Trash2, X } from '@lucide/vue';
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
    quote: { type: Object, required: true },
    salesOrder: { type: Object, default: null },
    isExpired: { type: Boolean, required: true },
});

const { can } = usePermissions();
// Computed: workflow actions reload props on this same page instance.
const q = computed(() => props.quote.data);
const status = computed(() => q.value.status);

const timeline = computed(() => [
    { label: 'Créé', date: q.value.created_at, done: true },
    { label: 'Envoyé', date: q.value.sent_at, done: !!q.value.sent_at },
    status.value === 'rejected'
        ? { label: 'Refusé', date: q.value.rejected_at, done: true, failed: true }
        : { label: 'Accepté', date: q.value.accepted_at, done: !!q.value.accepted_at },
    { label: 'Converti en commande', date: null, done: status.value === 'converted' },
]);

const { run, processing } = useAction();
const act = (action) => run(route(`sales.quotes.${action}`, q.value.id));
const destroy = () => router.delete(route('sales.quotes.destroy', q.value.id));
</script>

<template>
    <AppLayout :title="q.reference" :breadcrumbs="[{ title: 'Ventes' }, { title: 'Devis', href: route('sales.quotes.index') }, { title: q.reference }]">
        <PageHeader :title="q.reference" :description="`${q.customer?.name ?? ''} · ${formatDate(q.quote_date)}`">
            <template #actions>
                <Button v-if="status === 'draft' && can('quotes.send')" :disabled="processing" @click="act('send')"><Send /> Marquer envoyé</Button>
                <Button v-if="status === 'sent' && can('quotes.accept')" :disabled="processing" @click="act('accept')"><Check /> Accepté</Button>
                <ReasonDialog v-if="status === 'sent' && can('quotes.reject')" :url="route('sales.quotes.reject', q.id)"
                    title="Le client a refusé le devis" :description="`${q.reference} · ${q.customer?.name ?? ''}`"
                    confirm-label="Confirmer le refus" placeholder="Ex. : prix trop élevé, délai trop long">
                    <X /> Refusé
                </ReasonDialog>
                <Button v-if="status === 'accepted' && can('quotes.convert')" :disabled="processing" @click="act('convert')">
                    <PackageCheck /> Convertir en commande
                </Button>
                <Button v-if="status === 'draft' && can('quotes.update')" variant="outline" as-child>
                    <Link :href="route('sales.quotes.edit', q.id)"><Pencil /> Modifier</Link>
                </Button>
                <Button v-if="can('quotes.duplicate')" variant="outline" :disabled="processing" @click="act('duplicate')"><Copy /> Dupliquer</Button>
                <AlertDialog v-if="status !== 'converted' && can('quotes.delete')">
                    <AlertDialogTrigger as-child>
                        <Button variant="ghost" size="icon" aria-label="Supprimer"><Trash2 /></Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Supprimer le devis {{ q.reference }} ?</AlertDialogTitle>
                            <AlertDialogDescription>{{ q.customer?.name }} · {{ formatMoney(q.total_ttc) }}</AlertDialogDescription>
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
                <DocumentLinesTable :document="q" />

                <Card v-if="q.notes">
                    <CardHeader><CardTitle>Notes</CardTitle></CardHeader>
                    <CardContent class="text-sm whitespace-pre-line">{{ q.notes }}</CardContent>
                </Card>
            </div>

            <div class="grid content-start gap-4">
                <Card>
                    <CardHeader><CardTitle>Statut</CardTitle></CardHeader>
                    <CardContent class="grid gap-4 text-sm">
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge :variant="variant('quoteStatus', status)">{{ label('quoteStatus', status) }}</Badge>
                            <Badge v-if="isExpired" variant="destructive">Validité dépassée</Badge>
                        </div>
                        <StatusTimeline :steps="timeline" />
                        <p v-if="q.rejection_reason" class="rounded-md bg-muted p-3 whitespace-pre-line">{{ q.rejection_reason }}</p>
                        <p v-if="salesOrder" class="text-muted-foreground">
                            Commande
                            <Link v-if="can('sales_orders.view')" :href="route('sales.orders.show', salesOrder.id)" class="font-medium text-foreground hover:underline">{{ salesOrder.reference }}</Link>
                            <span v-else class="font-medium text-foreground">{{ salesOrder.reference }}</span>
                            créée.
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Informations</CardTitle></CardHeader>
                    <CardContent>
                        <dl class="grid gap-3 text-sm">
                            <div><dt class="text-muted-foreground">Client</dt>
                                <dd>
                                    <Link v-if="q.customer && can('customers.view')" :href="route('sales.customers.show', q.customer.id)" class="font-medium hover:underline">{{ q.customer.name }}</Link>
                                    <span v-else class="font-medium">{{ q.customer?.name ?? '—' }}</span>
                                </dd>
                            </div>
                            <div><dt class="text-muted-foreground">Date</dt><dd>{{ formatDate(q.quote_date) }}</dd></div>
                            <div><dt class="text-muted-foreground">Valable jusqu'au</dt><dd :class="isExpired ? 'font-medium text-destructive' : ''">{{ formatDate(q.validity_date) }}</dd></div>
                            <div><dt class="text-muted-foreground">Conditions de paiement</dt><dd>{{ q.payment_term?.name ?? '—' }}</dd></div>
                        </dl>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
