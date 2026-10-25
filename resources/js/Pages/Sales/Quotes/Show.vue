<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Check, Copy, PackageCheck, Pencil, Send, Trash2 } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import RejectDialog from './Partials/RejectDialog.vue';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription,
    AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useAction } from '@/composables/useAction';
import { usePermissions } from '@/composables/usePermissions';
import { formatDate, formatDateTime, formatMoney, label, variant } from '@/composables/format';

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
                <RejectDialog v-if="status === 'sent' && can('quotes.reject')" :quote="q" />
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
                <Card class="gap-4">
                    <CardHeader><CardTitle>Lignes</CardTitle></CardHeader>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="pl-6">Description</TableHead>
                                <TableHead class="text-right">Qté</TableHead>
                                <TableHead class="hidden text-right sm:table-cell">P.U. HT</TableHead>
                                <TableHead class="hidden text-right md:table-cell">Remise</TableHead>
                                <TableHead class="hidden text-right md:table-cell">TVA</TableHead>
                                <TableHead class="pr-6 text-right">Total TTC</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="line in q.lines" :key="line.id">
                                <TableCell class="max-w-72 pl-6 whitespace-normal">
                                    <p class="font-medium">{{ line.description }}</p>
                                    <p v-if="line.product" class="text-xs text-muted-foreground">{{ line.product.code }}</p>
                                </TableCell>
                                <TableCell class="text-right tabular-nums">{{ Number(line.quantity) }}</TableCell>
                                <TableCell class="hidden text-right tabular-nums sm:table-cell">{{ formatMoney(line.unit_price_ht) }}</TableCell>
                                <TableCell class="hidden text-right tabular-nums md:table-cell">{{ Number(line.discount_value) ? `${Number(line.discount_value)} %` : '—' }}</TableCell>
                                <TableCell class="hidden text-right tabular-nums md:table-cell">{{ Number(line.tax_rate) }} %</TableCell>
                                <TableCell class="pr-6 text-right font-medium tabular-nums">{{ formatMoney(line.total_ttc) }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <CardContent>
                        <Separator class="mb-4" />
                        <dl class="ml-auto grid w-full max-w-xs gap-1.5 text-sm">
                            <div class="flex justify-between"><dt class="text-muted-foreground">Sous-total HT</dt><dd class="tabular-nums">{{ formatMoney(q.subtotal_ht) }}</dd></div>
                            <div v-if="Number(q.total_discount)" class="flex justify-between"><dt class="text-muted-foreground">Remises</dt><dd class="tabular-nums">− {{ formatMoney(q.total_discount) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-muted-foreground">TVA</dt><dd class="tabular-nums">{{ formatMoney(q.total_tax) }}</dd></div>
                            <div class="flex justify-between border-t pt-1.5 text-base font-semibold"><dt>Total TTC</dt><dd class="tabular-nums">{{ formatMoney(q.total_ttc) }}</dd></div>
                        </dl>
                    </CardContent>
                </Card>

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
                        <ol class="grid gap-3">
                            <li v-for="step in timeline" :key="step.label" class="flex items-start gap-3">
                                <span class="mt-1 size-2.5 shrink-0 rounded-full"
                                    :class="step.failed ? 'bg-destructive' : step.done ? 'bg-primary' : 'border bg-background'" />
                                <div>
                                    <p :class="step.done ? 'font-medium' : 'text-muted-foreground'">{{ step.label }}</p>
                                    <p v-if="step.date" class="text-xs text-muted-foreground">{{ formatDateTime(step.date) }}</p>
                                </div>
                            </li>
                        </ol>
                        <p v-if="q.rejection_reason" class="rounded-md bg-muted p-3 whitespace-pre-line">{{ q.rejection_reason }}</p>
                        <p v-if="salesOrder" class="text-muted-foreground">
                            Commande <span class="font-medium text-foreground">{{ salesOrder.reference }}</span> créée.
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
