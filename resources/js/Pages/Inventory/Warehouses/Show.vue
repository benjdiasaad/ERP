<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { ClipboardCheck, MapPin, Pencil, Trash2, TriangleAlert, UserRound } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import MovementsTable from '@/components/inventory/MovementsTable.vue';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription,
    AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { usePermissions } from '@/composables/usePermissions';
import { formatNumber } from '@/composables/format';

const props = defineProps({
    warehouse: { type: Object, required: true },
    levels: { type: Array, required: true },
    movements: { type: Object, default: null },
});

const { can } = usePermissions();
const w = computed(() => props.warehouse.data);
const destroy = () => router.delete(route('stock.warehouses.destroy', w.value.id));
</script>

<template>
    <AppLayout :title="w.name" :breadcrumbs="[{ title: 'Stock' }, { title: 'Entrepôts', href: route('stock.warehouses.index') }, { title: w.name }]">
        <PageHeader :title="w.name" :description="w.code">
            <template #actions>
                <Button v-if="can('stock_inventories.create')" variant="outline" as-child>
                    <Link :href="route('stock.inventories.create', { warehouse_id: w.id })"><ClipboardCheck /> Inventaire</Link>
                </Button>
                <Button v-if="can('warehouses.update')" variant="outline" as-child>
                    <Link :href="route('stock.warehouses.edit', w.id)"><Pencil /> Modifier</Link>
                </Button>
                <AlertDialog v-if="can('warehouses.delete')">
                    <AlertDialogTrigger as-child>
                        <Button variant="ghost" size="icon" aria-label="Supprimer"><Trash2 /></Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Supprimer {{ w.name }} ?</AlertDialogTitle>
                            <AlertDialogDescription>L'entrepôt sera retiré des listes. L'historique des mouvements est conservé.</AlertDialogDescription>
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
                <CardHeader><CardTitle>Informations</CardTitle></CardHeader>
                <CardContent>
                    <dl class="grid gap-3 text-sm">
                        <div class="flex items-start gap-2"><MapPin class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                            <span>{{ [w.address, [w.postal_code, w.city].filter(Boolean).join(' '), w.country].filter(Boolean).join(', ') || '—' }}</span>
                        </div>
                        <div class="flex items-center gap-2"><UserRound class="size-4 shrink-0 text-muted-foreground" />
                            {{ w.manager ? `${w.manager.first_name} ${w.manager.last_name}` : 'Aucun responsable' }}
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <Badge v-if="w.is_default" variant="outline">Par défaut</Badge>
                            <Badge :variant="w.is_active ? 'secondary' : 'outline'">{{ w.is_active ? 'Actif' : 'Inactif' }}</Badge>
                        </div>
                        <div v-if="w.notes"><dt class="text-muted-foreground">Notes</dt><dd class="whitespace-pre-line">{{ w.notes }}</dd></div>
                    </dl>
                </CardContent>
            </Card>

            <div class="grid content-start gap-4 lg:col-span-2">
                <Card class="gap-4 pb-2">
                    <CardHeader><CardTitle>Stock ({{ levels.length }} produit{{ levels.length > 1 ? 's' : '' }})</CardTitle></CardHeader>
                    <Table v-if="levels.length">
                        <TableHeader>
                            <TableRow>
                                <TableHead class="pl-4">Produit</TableHead>
                                <TableHead class="text-right">En stock</TableHead>
                                <TableHead class="hidden pr-4 text-right sm:table-cell">Réservé</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="level in levels" :key="level.product?.id">
                                <TableCell class="pl-4">
                                    <Link v-if="level.product" :href="route('stock.products.show', level.product.id)" class="font-medium hover:underline">{{ level.product.name }}</Link>
                                    <p class="text-xs text-muted-foreground">{{ level.product?.code }}</p>
                                </TableCell>
                                <TableCell class="text-right tabular-nums" :class="level.low ? 'font-medium text-destructive' : ''">
                                    <TriangleAlert v-if="level.low" class="mr-1 inline size-3.5" aria-label="Stock bas" />
                                    {{ formatNumber(level.on_hand) }} <span class="text-xs text-muted-foreground">{{ level.product?.unit }}</span>
                                </TableCell>
                                <TableCell class="hidden pr-4 text-right tabular-nums text-muted-foreground sm:table-cell">{{ formatNumber(level.reserved) }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <CardContent v-else><p class="text-sm text-muted-foreground">Aucun produit en stock dans cet entrepôt.</p></CardContent>
                </Card>

                <Card v-if="movements" class="gap-4 pb-2">
                    <CardHeader><CardTitle>Derniers mouvements</CardTitle></CardHeader>
                    <MovementsTable v-if="movements.data.length" :movements="movements.data" :show-warehouse="false" />
                    <CardContent v-else><p class="text-sm text-muted-foreground">Aucun mouvement.</p></CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
