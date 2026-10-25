<script setup>
import { computed, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { Ban, CircleCheck, Save, Trash2 } from '@lucide/vue';
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
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useAction } from '@/composables/useAction';
import { usePermissions } from '@/composables/usePermissions';
import { formatDate, formatDateTime, label, variant } from '@/composables/format';

const props = defineProps({
    note: { type: Object, required: true },
    warehouses: { type: Array, default: () => [] },
});

const { can } = usePermissions();
// Computed: workflow actions reload props on this same page instance.
const n = computed(() => props.note.data);
const status = computed(() => n.value.status);
const editable = computed(() => status.value === 'draft' && can('reception_notes.update'));

const toForm = (lines) => lines.map((l) => ({
    id: l.id,
    purchase_order_line_id: l.purchase_order_line_id,
    product_id: l.product_id,
    description: l.description,
    ordered_quantity: Number(l.ordered_quantity),
    received_quantity: Number(l.received_quantity),
    rejected_quantity: Number(l.rejected_quantity ?? 0),
    unit: l.unit,
}));
const form = useForm({ warehouse_id: n.value.warehouse_id, lines: toForm(n.value.lines) });
watch(() => n.value, (note) => form.defaults({ warehouse_id: note.warehouse_id, lines: toForm(note.lines) }).reset());

const save = () => form.put(route('purchasing.receptions.update', n.value.id), { preserveScroll: true });

const { run, processing } = useAction();
// Unsaved quantity changes are saved before confirming.
const confirmReception = () => (form.isDirty
    ? form.put(route('purchasing.receptions.update', n.value.id), { preserveScroll: true, onSuccess: () => run(route('purchasing.receptions.confirm', n.value.id)) })
    : run(route('purchasing.receptions.confirm', n.value.id)));
const destroy = () => router.delete(route('purchasing.receptions.destroy', n.value.id));
const lineError = (index, field) => form.errors[`lines.${index}.${field}`];
</script>

<template>
    <AppLayout :title="n.reference" :breadcrumbs="[{ title: 'Achats' }, { title: 'Réceptions', href: route('purchasing.receptions.index') }, { title: n.reference }]">
        <PageHeader :title="n.reference" :description="`${n.supplier?.name ?? ''} · ${formatDate(n.reception_date)}`">
            <template #actions>
                <Button v-if="status === 'draft' && can('reception_notes.confirm')" :disabled="processing || form.processing" @click="confirmReception">
                    <CircleCheck /> Confirmer la réception
                </Button>
                <ReasonDialog v-if="status === 'draft' && can('reception_notes.cancel')" :url="route('purchasing.receptions.cancel', n.id)"
                    title="Annuler la réception" :description="n.reference" confirm-label="Annuler la réception" trigger-variant="ghost">
                    <Ban /> Annuler
                </ReasonDialog>
                <AlertDialog v-if="status === 'draft' && can('reception_notes.delete')">
                    <AlertDialogTrigger as-child>
                        <Button variant="ghost" size="icon" aria-label="Supprimer"><Trash2 /></Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Supprimer la réception {{ n.reference }} ?</AlertDialogTitle>
                            <AlertDialogDescription>Le bon de commande pourra être réceptionné à nouveau.</AlertDialogDescription>
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
            <Card class="min-w-0 gap-4 lg:col-span-2">
                <CardHeader>
                    <CardTitle>Articles</CardTitle>
                    <CardDescription v-if="editable">Saisissez les quantités réellement reçues et refusées, puis confirmez.</CardDescription>
                    <CardAction v-if="editable">
                        <Button variant="outline" size="sm" :disabled="!form.isDirty || form.processing" @click="save"><Save /> Enregistrer</Button>
                    </CardAction>
                </CardHeader>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="pl-6">Article</TableHead>
                            <TableHead class="text-right">Commandé</TableHead>
                            <TableHead class="text-right">Reçu</TableHead>
                            <TableHead class="pr-6 text-right">Refusé</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="(line, index) in form.lines" :key="line.id">
                            <TableCell class="max-w-64 pl-6 whitespace-normal font-medium">{{ line.description }}</TableCell>
                            <TableCell class="text-right tabular-nums text-muted-foreground">{{ line.ordered_quantity }} {{ line.unit }}</TableCell>
                            <TableCell class="text-right">
                                <Input v-if="editable" v-model="line.received_quantity" type="number" min="0" step="0.01" class="ml-auto w-24 text-right"
                                    :aria-label="`Quantité reçue : ${line.description}`" :aria-invalid="!!lineError(index, 'received_quantity') || undefined" />
                                <span v-else class="font-medium tabular-nums">{{ line.received_quantity }}</span>
                            </TableCell>
                            <TableCell class="pr-6 text-right">
                                <Input v-if="editable" v-model="line.rejected_quantity" type="number" min="0" step="0.01" class="ml-auto w-24 text-right"
                                    :aria-label="`Quantité refusée : ${line.description}`" />
                                <span v-else class="tabular-nums" :class="line.rejected_quantity ? 'text-destructive' : 'text-muted-foreground'">{{ line.rejected_quantity }}</span>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </Card>

            <div class="grid content-start gap-4">
                <Card>
                    <CardHeader><CardTitle>Statut</CardTitle></CardHeader>
                    <CardContent class="grid gap-3 text-sm">
                        <div><Badge :variant="variant('receptionStatus', status)">{{ label('receptionStatus', status) }}</Badge></div>
                        <p v-if="n.confirmed_at" class="text-muted-foreground">Confirmée le {{ formatDateTime(n.confirmed_at) }}</p>
                        <p v-if="n.cancellation_reason" class="rounded-md bg-muted p-3 whitespace-pre-line">{{ n.cancellation_reason }}</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader><CardTitle>Informations</CardTitle></CardHeader>
                    <CardContent>
                        <dl class="grid gap-3 text-sm">
                            <div><dt class="text-muted-foreground">Fournisseur</dt><dd class="font-medium">{{ n.supplier?.name ?? '—' }}</dd></div>
                            <div v-if="n.purchase_order"><dt class="text-muted-foreground">Bon de commande</dt>
                                <dd><Link :href="route('purchasing.orders.show', n.purchase_order.id)" class="hover:underline">{{ n.purchase_order.reference }}</Link></dd>
                            </div>
                            <div><dt class="text-muted-foreground">Date de réception</dt><dd>{{ formatDate(n.reception_date) }}</dd></div>
                            <FormField v-if="editable" id="warehouse_id" v-model="form.warehouse_id" label="Entrepôt de réception" :options="warehouses"
                                placeholder="Aucun (stock non suivi)" :error="form.errors.warehouse_id" />
                            <div v-else><dt class="text-muted-foreground">Entrepôt de réception</dt><dd>{{ n.warehouse?.name ?? 'Aucun (stock non suivi)' }}</dd></div>
                            <div v-if="n.notes"><dt class="text-muted-foreground">Notes</dt><dd class="whitespace-pre-line">{{ n.notes }}</dd></div>
                        </dl>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
