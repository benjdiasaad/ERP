<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { PackageCheck, PackageOpen, Pencil, Trash2, Undo2 } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import ReasonDialog from '@/components/ReasonDialog.vue';
import StatusTimeline from '@/components/sales/StatusTimeline.vue';
import ShipDialog from './Partials/ShipDialog.vue';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription,
    AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useAction } from '@/composables/useAction';
import { usePermissions } from '@/composables/usePermissions';
import { formatDate, label, variant } from '@/composables/format';

const props = defineProps({
    note: { type: Object, required: true },
});

const { can } = usePermissions();
// Computed: workflow actions reload props on this same page instance.
const n = computed(() => props.note.data);
const status = computed(() => n.value.status);
const qty = (value) => Number(value ?? 0);

const timeline = computed(() => [
    { label: 'Créé', date: n.value.created_at, done: true },
    { label: 'Prêt à expédier', date: null, done: status.value !== 'draft' },
    { label: 'Expédié', date: n.value.shipped_at, dateOnly: true, done: !!n.value.shipped_at },
    status.value === 'returned'
        ? { label: 'Retourné', date: null, done: true, failed: true }
        : { label: 'Livré', date: n.value.delivered_at, dateOnly: true, done: !!n.value.delivered_at },
]);

const { run, processing } = useAction();
const act = (action) => run(route(`sales.delivery-notes.${action}`, n.value.id));
const destroy = () => router.delete(route('sales.delivery-notes.destroy', n.value.id));
</script>

<template>
    <AppLayout :title="n.reference" :breadcrumbs="[{ title: 'Ventes' }, { title: 'Bons de livraison', href: route('sales.delivery-notes.index') }, { title: n.reference }]">
        <PageHeader :title="n.reference" :description="`${n.customer?.name ?? ''} · ${formatDate(n.date)}`">
            <template #actions>
                <Button v-if="status === 'draft' && can('delivery_notes.update')" :disabled="processing" @click="act('ready')"><PackageOpen /> Marquer prêt</Button>
                <ShipDialog v-if="status === 'ready' && can('delivery_notes.ship')" :note="n" />
                <Button v-if="status === 'shipped' && can('delivery_notes.deliver')" :disabled="processing" @click="act('deliver')"><PackageCheck /> Livré</Button>
                <ReasonDialog v-if="status === 'delivered' && can('delivery_notes.return')" :url="route('sales.delivery-notes.return', n.id)"
                    title="Enregistrer un retour" :description="`${n.reference} · ${n.customer?.name ?? ''}`" confirm-label="Confirmer le retour"
                    placeholder="Ex. : marchandise endommagée">
                    <Undo2 /> Retour
                </ReasonDialog>
                <Button v-if="status === 'draft' && can('delivery_notes.update')" variant="outline" as-child>
                    <Link :href="route('sales.delivery-notes.edit', n.id)"><Pencil /> Modifier</Link>
                </Button>
                <AlertDialog v-if="status === 'draft' && can('delivery_notes.delete')">
                    <AlertDialogTrigger as-child>
                        <Button variant="ghost" size="icon" aria-label="Supprimer"><Trash2 /></Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Supprimer le bon {{ n.reference }} ?</AlertDialogTitle>
                            <AlertDialogDescription>{{ n.customer?.name }}</AlertDialogDescription>
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
                    <CardHeader><CardTitle>Articles</CardTitle></CardHeader>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="pl-6">Description</TableHead>
                                <TableHead class="text-right">Commandé</TableHead>
                                <TableHead class="text-right">Livré</TableHead>
                                <TableHead class="pr-6">Unité</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="line in n.lines" :key="line.id">
                                <TableCell class="max-w-72 pl-6 whitespace-normal">
                                    <p class="font-medium">{{ line.description }}</p>
                                    <p v-if="line.product" class="text-xs text-muted-foreground">{{ line.product.code }}</p>
                                </TableCell>
                                <TableCell class="text-right tabular-nums text-muted-foreground">{{ qty(line.ordered_quantity) }}</TableCell>
                                <TableCell class="text-right font-medium tabular-nums">{{ qty(line.shipped_quantity) }}</TableCell>
                                <TableCell class="pr-6 text-muted-foreground">{{ line.unit ?? '—' }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </Card>

                <Card v-if="n.notes">
                    <CardHeader><CardTitle>Notes</CardTitle></CardHeader>
                    <CardContent class="text-sm whitespace-pre-line">{{ n.notes }}</CardContent>
                </Card>
            </div>

            <div class="grid content-start gap-4">
                <Card>
                    <CardHeader><CardTitle>Statut</CardTitle></CardHeader>
                    <CardContent class="grid gap-4 text-sm">
                        <div><Badge :variant="variant('deliveryStatus', status)">{{ label('deliveryStatus', status) }}</Badge></div>
                        <StatusTimeline :steps="timeline" />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Informations</CardTitle></CardHeader>
                    <CardContent>
                        <dl class="grid gap-3 text-sm">
                            <div><dt class="text-muted-foreground">Client</dt>
                                <dd>
                                    <Link v-if="n.customer && can('customers.view')" :href="route('sales.customers.show', n.customer.id)" class="font-medium hover:underline">{{ n.customer.name }}</Link>
                                    <span v-else class="font-medium">{{ n.customer?.name ?? '—' }}</span>
                                </dd>
                            </div>
                            <div v-if="n.sales_order"><dt class="text-muted-foreground">Commande</dt>
                                <dd><Link :href="route('sales.orders.show', n.sales_order.id)" class="hover:underline">{{ n.sales_order.reference }}</Link></dd>
                            </div>
                            <div><dt class="text-muted-foreground">Adresse de livraison</dt><dd class="whitespace-pre-line">{{ n.delivery_address ?? '—' }}</dd></div>
                            <div><dt class="text-muted-foreground">Livraison prévue</dt><dd>{{ formatDate(n.expected_delivery_date) }}</dd></div>
                            <div><dt class="text-muted-foreground">Transporteur</dt><dd>{{ n.carrier ?? '—' }}</dd></div>
                            <div><dt class="text-muted-foreground">N° de suivi</dt><dd class="break-all">{{ n.tracking_number ?? '—' }}</dd></div>
                        </dl>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
