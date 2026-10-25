<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Ban, Check, Pencil, Send, Trash2, X } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import ReasonDialog from '@/components/ReasonDialog.vue';
import StatusTimeline from '@/components/sales/StatusTimeline.vue';
import ConvertDialog from './Partials/ConvertDialog.vue';
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
import { formatDate, formatMoney, label, variant } from '@/composables/format';

const props = defineProps({
    request: { type: Object, required: true },
    order: { type: Object, default: null },
    suppliers: { type: Array, required: true },
});

const { can } = usePermissions();
// Computed: workflow actions reload props on this same page instance.
const r = computed(() => props.request.data);
const status = computed(() => r.value.status);
const total = computed(() => r.value.lines.reduce((sum, l) => sum + Number(l.estimated_total ?? 0), 0));

const timeline = computed(() => [
    { label: 'Créée', date: r.value.created_at, done: true },
    { label: 'Soumise', date: r.value.submitted_at, done: !!r.value.submitted_at },
    status.value === 'rejected'
        ? { label: 'Rejetée', date: r.value.rejected_at, done: true, failed: true }
        : { label: 'Approuvée', date: r.value.approved_at, done: !!r.value.approved_at },
    { label: 'Commandée', date: null, done: status.value === 'converted' },
]);

const { run, processing } = useAction();
const act = (action) => run(route(`purchasing.requests.${action}`, r.value.id));
const destroy = () => router.delete(route('purchasing.requests.destroy', r.value.id));
</script>

<template>
    <AppLayout :title="r.title" :breadcrumbs="[{ title: 'Achats' }, { title: 'Demandes d\'achat', href: route('purchasing.requests.index') }, { title: r.reference }]">
        <PageHeader :title="r.title" :description="`${r.reference} · créée le ${formatDate(r.created_at)}`">
            <template #actions>
                <Button v-if="status === 'draft' && can('purchase_requests.submit')" :disabled="processing" @click="act('submit')"><Send /> Soumettre</Button>
                <Button v-if="status === 'submitted' && can('purchase_requests.approve')" :disabled="processing" @click="act('approve')"><Check /> Approuver</Button>
                <ReasonDialog v-if="status === 'submitted' && can('purchase_requests.reject')" :url="route('purchasing.requests.reject', r.id)"
                    title="Rejeter la demande" :description="r.title" confirm-label="Rejeter" placeholder="Ex. : hors budget">
                    <X /> Rejeter
                </ReasonDialog>
                <ConvertDialog v-if="status === 'approved' && can('purchase_requests.convert')" :request="r" :suppliers="suppliers" />
                <Button v-if="status === 'draft' && can('purchase_requests.update')" variant="outline" as-child>
                    <Link :href="route('purchasing.requests.edit', r.id)"><Pencil /> Modifier</Link>
                </Button>
                <Button v-if="['draft', 'submitted', 'approved'].includes(status) && can('purchase_requests.cancel')" variant="ghost" :disabled="processing" @click="act('cancel')">
                    <Ban /> Annuler
                </Button>
                <AlertDialog v-if="status === 'draft' && can('purchase_requests.delete')">
                    <AlertDialogTrigger as-child>
                        <Button variant="ghost" size="icon" aria-label="Supprimer"><Trash2 /></Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Supprimer la demande {{ r.reference }} ?</AlertDialogTitle>
                            <AlertDialogDescription>{{ r.title }}</AlertDialogDescription>
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
                    <CardHeader><CardTitle>Articles demandés</CardTitle></CardHeader>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="pl-6">Article</TableHead>
                                <TableHead class="text-right">Qté</TableHead>
                                <TableHead class="hidden text-right sm:table-cell">Prix estimé HT</TableHead>
                                <TableHead class="pr-6 text-right">Total HT</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="line in r.lines" :key="line.id">
                                <TableCell class="max-w-72 pl-6 whitespace-normal font-medium">{{ line.description }}</TableCell>
                                <TableCell class="text-right tabular-nums">{{ Number(line.quantity) }} {{ line.unit }}</TableCell>
                                <TableCell class="hidden text-right tabular-nums sm:table-cell">{{ formatMoney(line.estimated_unit_price) }}</TableCell>
                                <TableCell class="pr-6 text-right font-medium tabular-nums">{{ formatMoney(line.estimated_total) }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <CardContent class="text-right text-sm">Estimation totale HT : <span class="font-semibold tabular-nums">{{ formatMoney(total) }}</span></CardContent>
                </Card>

                <Card v-if="r.description">
                    <CardHeader><CardTitle>Justification</CardTitle></CardHeader>
                    <CardContent class="text-sm whitespace-pre-line">{{ r.description }}</CardContent>
                </Card>
            </div>

            <div class="grid content-start gap-4">
                <Card>
                    <CardHeader><CardTitle>Statut</CardTitle></CardHeader>
                    <CardContent class="grid gap-4 text-sm">
                        <div class="flex flex-wrap gap-2">
                            <Badge :variant="variant('purchaseRequestStatus', status)">{{ label('purchaseRequestStatus', status) }}</Badge>
                            <Badge :variant="variant('priority', r.priority)">Priorité {{ label('priority', r.priority).toLowerCase() }}</Badge>
                        </div>
                        <StatusTimeline :steps="timeline" />
                        <p v-if="r.rejection_reason" class="rounded-md bg-muted p-3 whitespace-pre-line">{{ r.rejection_reason }}</p>
                        <p v-if="order" class="text-muted-foreground">Bon de commande
                            <Link :href="route('purchasing.orders.show', order.id)" class="font-medium text-foreground hover:underline">{{ order.reference }}</Link>
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Informations</CardTitle></CardHeader>
                    <CardContent>
                        <dl class="grid gap-3 text-sm">
                            <div><dt class="text-muted-foreground">Demandeur</dt><dd>{{ r.requested_by_user?.name ?? '—' }}</dd></div>
                            <div><dt class="text-muted-foreground">Souhaitée pour le</dt><dd>{{ formatDate(r.required_date) }}</dd></div>
                            <div><dt class="text-muted-foreground">Fournisseur</dt><dd>{{ r.supplier?.name ?? 'À choisir' }}</dd></div>
                            <div v-if="r.approved_by_user"><dt class="text-muted-foreground">Approuvée par</dt><dd>{{ r.approved_by_user.name }}</dd></div>
                        </dl>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
