<script setup>
import { Link } from '@inertiajs/vue3';
import { Package, Plus, Search, TriangleAlert } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import DataPagination from '@/components/DataPagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useFilters } from '@/composables/useFilters';
import { usePermissions } from '@/composables/usePermissions';
import { formatMoney, formatNumber, label, labels } from '@/composables/format';

const props = defineProps({
    products: { type: Object, required: true },
    stock: { type: Object, required: true },
    categories: { type: Array, required: true },
    filters: { type: Object, required: true },
});

const { can } = usePermissions();
const filters = useFilters('stock.products.index', props.filters, { search: '', category_id: '', type: '', status: '' });
</script>

<template>
    <AppLayout title="Produits" :breadcrumbs="[{ title: 'Stock' }, { title: 'Produits' }]">
        <PageHeader title="Produits" description="Articles achetés, vendus et stockés par la société.">
            <template #actions>
                <Button v-if="can('products.create')" as-child>
                    <Link :href="route('stock.products.create')"><Plus /> Nouveau produit</Link>
                </Button>
            </template>
        </PageHeader>

        <Card class="gap-4 py-4">
            <CardContent class="flex flex-col gap-3 px-4 sm:flex-row">
                <div class="relative flex-1">
                    <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="filters.search" type="search" placeholder="Nom, code, code-barres…" class="pl-8" aria-label="Rechercher" />
                </div>
                <NativeSelect v-model="filters.category_id" aria-label="Catégorie" class="sm:w-44">
                    <option value="">Toutes catégories</option>
                    <option v-for="c in categories" :key="c.value" :value="c.value">{{ c.label }}</option>
                </NativeSelect>
                <NativeSelect v-model="filters.type" aria-label="Type" class="sm:w-36">
                    <option value="">Tous types</option>
                    <option v-for="(text, value) in labels.productType" :key="value" :value="value">{{ text }}</option>
                </NativeSelect>
                <NativeSelect v-model="filters.status" aria-label="Statut" class="sm:w-32">
                    <option value="">Tous</option>
                    <option value="active">Actifs</option>
                    <option value="inactive">Inactifs</option>
                </NativeSelect>
            </CardContent>

            <Table v-if="products.data.length">
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Produit</TableHead>
                        <TableHead class="hidden md:table-cell">Catégorie</TableHead>
                        <TableHead class="hidden text-right lg:table-cell">Prix d'achat</TableHead>
                        <TableHead class="hidden text-right sm:table-cell">Prix de vente</TableHead>
                        <TableHead class="text-right">Stock</TableHead>
                        <TableHead class="pr-4">Statut</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="product in products.data" :key="product.id">
                        <TableCell class="pl-4">
                            <Link :href="route('stock.products.show', product.id)" class="font-medium hover:underline">{{ product.name }}</Link>
                            <p class="text-xs text-muted-foreground">{{ product.code }} · {{ label('productType', product.type) }}</p>
                        </TableCell>
                        <TableCell class="hidden text-muted-foreground md:table-cell">{{ product.category?.name ?? '—' }}</TableCell>
                        <TableCell class="hidden text-right tabular-nums lg:table-cell">{{ formatMoney(product.purchase_price) }}</TableCell>
                        <TableCell class="hidden text-right tabular-nums sm:table-cell">{{ formatMoney(product.sale_price) }}</TableCell>
                        <TableCell class="text-right tabular-nums">
                            <template v-if="product.is_stockable">
                                <span :class="stock[product.id]?.low ? 'font-medium text-destructive' : ''">
                                    <TriangleAlert v-if="stock[product.id]?.low" class="mr-1 inline size-3.5" aria-label="Stock bas" />
                                    {{ formatNumber(stock[product.id]?.on_hand) }}
                                </span>
                                <span class="text-xs text-muted-foreground"> {{ product.unit }}</span>
                            </template>
                            <span v-else class="text-muted-foreground">—</span>
                        </TableCell>
                        <TableCell class="pr-4"><Badge :variant="product.is_active ? 'secondary' : 'outline'">{{ product.is_active ? 'Actif' : 'Inactif' }}</Badge></TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <Empty v-else>
                <EmptyHeader>
                    <EmptyMedia variant="icon"><Package /></EmptyMedia>
                    <EmptyTitle>Aucun produit</EmptyTitle>
                    <EmptyDescription>Aucun produit ne correspond à ces filtres.</EmptyDescription>
                </EmptyHeader>
            </Empty>

            <DataPagination :meta="products.meta" class="px-4" />
        </Card>
    </AppLayout>
</template>
