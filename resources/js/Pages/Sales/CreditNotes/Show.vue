<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { CircleCheck, Pencil, Trash2 } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import DocumentLinesTable from '@/components/sales/DocumentLinesTable.vue';
import StatusTimeline from '@/components/sales/StatusTimeline.vue';
import ApplyDialog from './Partials/ApplyDialog.vue';
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
    note: { type: Object, required: true },
    invoices: { type: Array, required: true },
});

const { can } = usePermissions();
// Computed: workflow actions reload props on this same page instance.
const n = computed(() => props.note.data);
const status = computed(() => n.value.status);

const timeline = computed(() => [
    { label: 'Créé', date: n.value.created_at, done: true },
    { label: 'Confirmé', date: n.value.confirmed_at, done: !!n.value.confirmed_at },
    { label: 'Imputé sur facture', date: n.value.applied_at, done: !!n.value.applied_at },
]);

const { run, processing } = useAction();
const confirm = () => run(route('sales.credit-notes.confirm', n.value.id));
const destroy = () => router.delete(route('sales.credit-notes.destroy', n.value.id));
</script>

<template>
    <AppLayout :title="n.reference" :breadcrumbs="[{ title: 'Ventes' }, { title: 'Avoirs', href: route('sales.credit-notes.index') }, { title: n.reference }]">
        <PageHeader :title="n.reference" :description="`${n.customer?.name ?? ''} · ${formatDate(n.date)}`">
            <template #actions>
                <Button v-if="status === 'draft' && can('credit_notes.confirm')" :disabled="processing" @click="confirm"><CircleCheck /> Confirmer</Button>
                <ApplyDialog v-if="status === 'confirmed' && can('credit_notes.apply')" :note="n" :invoices="invoices" />
                <Button v-if="status === 'draft' && can('credit_notes.update')" variant="outline" as-child>
                    <Link :href="route('sales.credit-notes.edit', n.id)"><Pencil /> Modifier</Link>
                </Button>
                <AlertDialog v-if="status === 'draft' && can('credit_notes.delete')">
                    <AlertDialogTrigger as-child>
                        <Button variant="ghost" size="icon" aria-label="Supprimer"><Trash2 /></Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Supprimer l'avoir {{ n.reference }} ?</AlertDialogTitle>
                            <AlertDialogDescription>{{ n.customer?.name }} · {{ formatMoney(n.total_ttc) }}</AlertDialogDescription>
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
                <DocumentLinesTable :document="n" />
            </div>

            <div class="grid content-start gap-4">
                <Card>
                    <CardHeader><CardTitle>Statut</CardTitle></CardHeader>
                    <CardContent class="grid gap-4 text-sm">
                        <div><Badge :variant="variant('creditNoteStatus', status)">{{ label('creditNoteStatus', status) }}</Badge></div>
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
                            <div v-if="n.invoice"><dt class="text-muted-foreground">Facture</dt>
                                <dd>
                                    <Link :href="route('sales.invoices.show', n.invoice.id)" class="hover:underline">{{ n.invoice.reference }}</Link>
                                    <span v-if="status !== 'applied'" class="text-muted-foreground"> · reste dû {{ formatMoney(n.invoice.amount_due) }}</span>
                                </dd>
                            </div>
                            <div v-if="n.reason"><dt class="text-muted-foreground">Motif</dt><dd class="whitespace-pre-line">{{ n.reason }}</dd></div>
                            <div v-if="n.notes"><dt class="text-muted-foreground">Notes</dt><dd class="whitespace-pre-line">{{ n.notes }}</dd></div>
                        </dl>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
