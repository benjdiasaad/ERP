<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { Ban, CircleCheck, Play, Plus, Save, Trash2, X } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import ReasonDialog from '@/components/ReasonDialog.vue';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription,
    AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useAction } from '@/composables/useAction';
import { usePermissions } from '@/composables/usePermissions';
import { formatDate, formatDateTime, formatNumber, label, variant } from '@/composables/format';

const props = defineProps({
    inventory: { type: Object, required: true },
    products: { type: Array, default: () => [] },
});

const { can } = usePermissions();
// Computed: workflow actions reload props on this same page instance.
const inv = computed(() => props.inventory.data);
const status = computed(() => inv.value.status);
const canEdit = computed(() => can('stock_inventories.update'));

// Draft: which products are on the sheet.
const toSheet = (lines) => lines.map((l) => ({ id: l.id, product_id: l.product_id, product: l.product, theoretical_quantity: l.theoretical_quantity }));
const sheet = useForm({ lines: toSheet(inv.value.lines) });
const productToAdd = ref(null);
const available = computed(() => props.products.filter((p) => !sheet.lines.some((l) => l.product_id === p.value)));
const addProduct = () => {
    const option = props.products.find((p) => p.value === productToAdd.value);
    if (!option) return;
    sheet.lines.push({ id: null, product_id: option.value, product: { name: option.label, unit: option.unit }, theoretical_quantity: null });
    productToAdd.value = null;
};
const saveSheet = (options = {}) => sheet
    .transform((data) => ({ lines: data.lines.map((l) => (l.id ? { id: l.id, product_id: l.product_id } : { product_id: l.product_id })) }))
    .put(route('stock.inventories.update', inv.value.id), { preserveScroll: true, ...options });

// In progress: counted quantities.
const toCounts = (lines) => lines.map((l) => ({ id: l.id, counted_quantity: Number(l.counted_quantity), notes: l.notes ?? '' }));
const counts = useForm({ lines: toCounts(inv.value.lines) });
const saveCounts = (options = {}) => counts.put(route('stock.inventories.update', inv.value.id), { preserveScroll: true, ...options });

watch(() => inv.value.lines, (lines) => {
    sheet.defaults({ lines: toSheet(lines) }).reset();
    counts.defaults({ lines: toCounts(lines) }).reset();
});

const varianceOf = (line, index) => {
    const counted = status.value === 'in_progress' ? Number(counts.lines[index]?.counted_quantity ?? 0) : Number(line.counted_quantity);
    return counted - Number(line.theoretical_quantity);
};
const linesWithVariance = computed(() => inv.value.lines.filter((l, i) => varianceOf(l, i) !== 0).length);

const { run, processing } = useAction();
// Unsaved changes are saved before moving to the next step.
const start = () => (sheet.isDirty
    ? saveSheet({ onSuccess: () => run(route('stock.inventories.start', inv.value.id)) })
    : run(route('stock.inventories.start', inv.value.id)));
const validate = () => (counts.isDirty
    ? saveCounts({ onSuccess: () => run(route('stock.inventories.validate', inv.value.id)) })
    : run(route('stock.inventories.validate', inv.value.id)));
const destroy = () => router.delete(route('stock.inventories.destroy', inv.value.id));
const signed = (value) => (value > 0 ? `+${formatNumber(value)}` : formatNumber(value));
</script>

<template>
    <AppLayout :title="inv.reference" :breadcrumbs="[{ title: 'Stock' }, { title: 'Inventaires', href: route('stock.inventories.index') }, { title: inv.reference }]">
        <PageHeader :title="inv.reference" :description="`${inv.warehouse?.name ?? ''} · ${formatDate(inv.count_date)}`">
            <template #actions>
                <Badge :variant="variant('inventoryStatus', status)">{{ label('inventoryStatus', status) }}</Badge>
                <Button v-if="status === 'draft' && can('stock_inventories.start')" :disabled="processing || sheet.processing || !sheet.lines.length" @click="start">
                    <Play /> Démarrer le comptage
                </Button>
                <AlertDialog v-if="status === 'in_progress' && can('stock_inventories.validate')">
                    <AlertDialogTrigger as-child>
                        <Button :disabled="processing || counts.processing"><CircleCheck /> Valider l'inventaire</Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Valider l'inventaire {{ inv.reference }} ?</AlertDialogTitle>
                            <AlertDialogDescription>
                                Le stock de {{ linesWithVariance }} produit(s) sera ajusté sur les quantités comptées. Cette action est définitive.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Annuler</AlertDialogCancel>
                            <AlertDialogAction @click="validate">Valider</AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
                <ReasonDialog v-if="['draft', 'in_progress'].includes(status) && can('stock_inventories.cancel')" :url="route('stock.inventories.cancel', inv.id)"
                    title="Annuler l'inventaire" :description="inv.reference" confirm-label="Annuler l'inventaire" trigger-variant="ghost">
                    <Ban /> Annuler
                </ReasonDialog>
                <AlertDialog v-if="status === 'draft' && can('stock_inventories.delete')">
                    <AlertDialogTrigger as-child>
                        <Button variant="ghost" size="icon" aria-label="Supprimer"><Trash2 /></Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Supprimer l'inventaire {{ inv.reference }} ?</AlertDialogTitle>
                            <AlertDialogDescription>La feuille de comptage sera supprimée. Le stock n'est pas modifié.</AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Annuler</AlertDialogCancel>
                            <AlertDialogAction class="bg-destructive text-white hover:bg-destructive/90" @click="destroy">Supprimer</AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </template>
        </PageHeader>

        <!-- Draft: build the sheet -->
        <Card v-if="status === 'draft'" class="gap-4 pb-2">
            <CardHeader>
                <CardTitle>Produits à compter ({{ sheet.lines.length }})</CardTitle>
                <CardDescription>Les quantités théoriques sont relevées au démarrage du comptage.</CardDescription>
                <CardAction v-if="canEdit">
                    <Button variant="outline" :disabled="!sheet.isDirty || sheet.processing" @click="saveSheet()"><Save /> Enregistrer</Button>
                </CardAction>
            </CardHeader>
            <CardContent v-if="canEdit" class="flex flex-col gap-2 sm:flex-row">
                <NativeSelect v-model="productToAdd" aria-label="Produit à ajouter" class="w-full sm:max-w-sm">
                    <option :value="null">Ajouter un produit…</option>
                    <option v-for="p in available" :key="p.value" :value="p.value">{{ p.label }}</option>
                </NativeSelect>
                <Button variant="secondary" :disabled="!productToAdd" @click="addProduct"><Plus /> Ajouter</Button>
            </CardContent>
            <p v-if="sheet.errors.lines" class="px-6 text-sm text-destructive">{{ sheet.errors.lines }}</p>
            <Table v-if="sheet.lines.length">
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Produit</TableHead>
                        <TableHead class="text-right">Stock actuel</TableHead>
                        <TableHead class="pr-4"><span class="sr-only">Retirer</span></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="(line, index) in sheet.lines" :key="line.id ?? `new-${line.product_id}`">
                        <TableCell class="pl-4">
                            <p class="font-medium">{{ line.product?.name }}</p>
                            <p class="text-xs text-muted-foreground">{{ line.product?.code }}</p>
                        </TableCell>
                        <TableCell class="text-right tabular-nums text-muted-foreground">
                            {{ line.theoretical_quantity === null ? '—' : formatNumber(line.theoretical_quantity) }} {{ line.product?.unit }}
                        </TableCell>
                        <TableCell class="pr-4 text-right">
                            <Button v-if="canEdit" variant="ghost" size="icon" :aria-label="`Retirer ${line.product?.name}`" @click="sheet.lines.splice(index, 1)"><X /></Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
            <CardContent v-else><p class="text-sm text-muted-foreground">Ajoutez au moins un produit pour démarrer le comptage.</p></CardContent>
        </Card>

        <!-- In progress / done: the count -->
        <Card v-else class="gap-4 pb-2">
            <CardHeader>
                <CardTitle>Comptage</CardTitle>
                <CardDescription>
                    <template v-if="status === 'in_progress'">Démarré le {{ formatDateTime(inv.counted_at) }} · saisissez les quantités réellement présentes.</template>
                    <template v-else-if="status === 'validated'">Validé le {{ formatDateTime(inv.validated_at) }} · {{ linesWithVariance }} écart(s) ajusté(s).</template>
                    <template v-else>Inventaire annulé : le stock n'a pas été modifié.</template>
                </CardDescription>
                <CardAction v-if="status === 'in_progress' && canEdit">
                    <Button variant="outline" :disabled="!counts.isDirty || counts.processing" @click="saveCounts()"><Save /> Enregistrer</Button>
                </CardAction>
            </CardHeader>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Produit</TableHead>
                        <TableHead class="text-right">Théorique</TableHead>
                        <TableHead class="text-right">Compté</TableHead>
                        <TableHead class="text-right">Écart</TableHead>
                        <TableHead class="hidden pr-4 md:table-cell">Notes</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="(line, index) in inv.lines" :key="line.id">
                        <TableCell class="pl-4">
                            <p class="font-medium">{{ line.product?.name }}</p>
                            <p class="text-xs text-muted-foreground">{{ line.product?.code }}</p>
                        </TableCell>
                        <TableCell class="text-right tabular-nums text-muted-foreground">{{ formatNumber(line.theoretical_quantity) }}</TableCell>
                        <TableCell class="text-right tabular-nums">
                            <Input v-if="status === 'in_progress' && canEdit" v-model="counts.lines[index].counted_quantity" type="number" min="0" step="any"
                                class="ml-auto w-24 text-right" :aria-label="`Quantité comptée ${line.product?.name}`"
                                :aria-invalid="!!counts.errors[`lines.${index}.counted_quantity`] || undefined" />
                            <template v-else>{{ formatNumber(line.counted_quantity) }}</template>
                            <span class="sr-only">{{ line.product?.unit }}</span>
                        </TableCell>
                        <TableCell class="text-right tabular-nums" :class="varianceOf(line, index) < 0 ? 'text-destructive' : varianceOf(line, index) > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground'">
                            {{ signed(varianceOf(line, index)) }}
                        </TableCell>
                        <TableCell class="hidden pr-4 md:table-cell">
                            <Input v-if="status === 'in_progress' && canEdit" v-model="counts.lines[index].notes" placeholder="—" :aria-label="`Notes ${line.product?.name}`" />
                            <span v-else class="text-muted-foreground">{{ line.notes ?? '—' }}</span>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </Card>

        <Card v-if="inv.notes">
            <CardHeader><CardTitle>Notes</CardTitle></CardHeader>
            <CardContent><p class="text-sm whitespace-pre-line">{{ inv.notes }}</p></CardContent>
        </Card>
    </AppLayout>
</template>
