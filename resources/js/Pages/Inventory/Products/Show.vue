<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { ArrowLeftRight, Pencil, Trash2, TriangleAlert } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import MovementsTable from '@/components/inventory/MovementsTable.vue';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription,
    AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { usePermissions } from '@/composables/usePermissions';
import { formatDateTime, formatMoney, formatNumber, formatPercent, label } from '@/composables/format';

const props = defineProps({
    product: { type: Object, required: true },
    levels: { type: Array, required: true },
    movements: { type: Object, default: null },
});

const { can } = usePermissions();
const p = computed(() => props.product.data);
const totalOnHand = computed(() => props.levels.reduce((sum, l) => sum + l.on_hand, 0));
const isLow = computed(() => p.value.is_stockable && Number(p.value.reorder_point) > 0 && totalOnHand.value <= Number(p.value.reorder_point));
const destroy = () => router.delete(route('stock.products.destroy', p.value.id));
</script>

<template>
    <AppLayout :title="p.name" :breadcrumbs="[{ title: 'Stock' }, { title: 'Produits', href: route('stock.products.index') }, { title: p.name }]">
        <PageHeader :title="p.name" :description="`${p.code} · ${label('productType', p.type)}${p.category ? ' · ' + p.category.name : ''}`">
            <template #actions>
                <Button v-if="p.is_stockable && can('stock_movements.view_any')" variant="outline" as-child>
                    <Link :href="route('stock.movements.index', { search: p.code })"><ArrowLeftRight /> Mouvements</Link>
                </Button>
                <Button v-if="can('products.update')" variant="outline" as-child>
                    <Link :href="route('stock.products.edit', p.id)"><Pencil /> Modifier</Link>
                </Button>
                <AlertDialog v-if="can('products.delete')">
                    <AlertDialogTrigger as-child>
                        <Button variant="ghost" size="icon" aria-label="Supprimer"><Trash2 /></Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Supprimer {{ p.name }} ?</AlertDialogTitle>
                            <AlertDialogDescription>Le produit sera retiré des listes. Les documents et mouvements existants sont conservés.</AlertDialogDescription>
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
            <Card class="self-start">
                <CardHeader><CardTitle>Fiche produit</CardTitle></CardHeader>
                <CardContent>
                    <dl class="grid gap-3 text-sm">
                        <div class="grid grid-cols-2 gap-2">
                            <div><dt class="text-muted-foreground">Prix d'achat HT</dt><dd class="tabular-nums">{{ formatMoney(p.purchase_price) }}</dd></div>
                            <div><dt class="text-muted-foreground">Prix de vente HT</dt><dd class="tabular-nums">{{ formatMoney(p.sale_price) }}</dd></div>
                            <div><dt class="text-muted-foreground">TVA</dt><dd>{{ formatPercent(p.tax_rate) }}</dd></div>
                            <div><dt class="text-muted-foreground">Unité</dt><dd>{{ p.unit ?? '—' }}</dd></div>
                        </div>
                        <Separator />
                        <div class="grid grid-cols-3 gap-2">
                            <div><dt class="text-muted-foreground">Minimum</dt><dd class="tabular-nums">{{ formatNumber(p.min_stock_level) }}</dd></div>
                            <div><dt class="text-muted-foreground">Cde à</dt><dd class="tabular-nums">{{ formatNumber(p.reorder_point) }}</dd></div>
                            <div><dt class="text-muted-foreground">Maximum</dt><dd class="tabular-nums">{{ formatNumber(p.max_stock_level) }}</dd></div>
                        </div>
                        <div v-if="p.barcode"><dt class="text-muted-foreground">Code-barres</dt><dd class="break-all">{{ p.barcode }}</dd></div>
                        <div class="flex flex-wrap gap-1.5">
                            <Badge v-if="p.is_purchasable" variant="outline">Acheté</Badge>
                            <Badge v-if="p.is_sellable" variant="outline">Vendu</Badge>
                            <Badge v-if="p.is_stockable" variant="outline">Stocké</Badge>
                            <Badge :variant="p.is_active ? 'secondary' : 'outline'">{{ p.is_active ? 'Actif' : 'Inactif' }}</Badge>
                        </div>
                        <div v-if="p.description"><dt class="text-muted-foreground">Description</dt><dd class="whitespace-pre-line">{{ p.description }}</dd></div>
                        <div v-if="p.notes"><dt class="text-muted-foreground">Notes</dt><dd class="whitespace-pre-line">{{ p.notes }}</dd></div>
                    </dl>
                </CardContent>
            </Card>

            <div class="grid content-start gap-4 lg:col-span-2">
                <Card v-if="p.is_stockable" class="gap-4">
                    <CardHeader>
                        <CardTitle>Stock par entrepôt</CardTitle>
                        <CardAction class="flex items-center gap-2 text-sm">
                            <TriangleAlert v-if="isLow" class="size-4 text-destructive" />
                            <span :class="isLow ? 'font-medium text-destructive' : 'text-muted-foreground'">
                                Total : {{ formatNumber(totalOnHand) }} {{ p.unit }}
                            </span>
                        </CardAction>
                    </CardHeader>
                    <CardContent>
                        <ul v-if="levels.length" class="grid gap-3">
                            <li v-for="level in levels" :key="level.warehouse?.id" class="flex items-center justify-between gap-3 text-sm">
                                <div>
                                    <Link v-if="level.warehouse" :href="route('stock.warehouses.show', level.warehouse.id)" class="font-medium hover:underline">{{ level.warehouse.name }}</Link>
                                    <p class="text-xs text-muted-foreground">Dernier mouvement : {{ formatDateTime(level.last_movement_at) }}</p>
                                </div>
                                <div class="text-right tabular-nums">
                                    <p class="font-medium">{{ formatNumber(level.on_hand) }} {{ p.unit }}</p>
                                    <p v-if="level.reserved" class="text-xs text-muted-foreground">{{ formatNumber(level.reserved) }} réservé(s)</p>
                                </div>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-muted-foreground">Aucun stock enregistré pour ce produit.</p>
                    </CardContent>
                </Card>

                <Card v-if="p.is_stockable && movements" class="gap-4 pb-2">
                    <CardHeader><CardTitle>Derniers mouvements</CardTitle></CardHeader>
                    <MovementsTable v-if="movements.data.length" :movements="movements.data" :show-product="false" />
                    <CardContent v-else><p class="text-sm text-muted-foreground">Aucun mouvement.</p></CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
