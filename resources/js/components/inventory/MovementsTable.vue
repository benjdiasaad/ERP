<script setup>
import { Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatDateTime, formatNumber, label, variant } from '@/composables/format';

/**
 * Stock ledger rows. Hide the product or warehouse column when the page is already about one.
 */
defineProps({
    movements: { type: Array, required: true },
    showProduct: { type: Boolean, default: true },
    showWarehouse: { type: Boolean, default: true },
});

// Signed quantity as it affects the stock (adjustments set an absolute quantity).
const sign = (type) => ({ in: '+', return: '+', out: '−', transfer: '−' }[type] ?? '');
</script>

<template>
    <Table>
        <TableHeader>
            <TableRow>
                <TableHead class="pl-4">Date</TableHead>
                <TableHead v-if="showProduct">Produit</TableHead>
                <TableHead v-if="showWarehouse" class="hidden md:table-cell">Entrepôt</TableHead>
                <TableHead>Type</TableHead>
                <TableHead class="text-right">Quantité</TableHead>
                <TableHead class="hidden pr-4 lg:table-cell">Origine</TableHead>
            </TableRow>
        </TableHeader>
        <TableBody>
            <TableRow v-for="m in movements" :key="m.id">
                <TableCell class="pl-4 whitespace-nowrap text-muted-foreground">{{ formatDateTime(m.created_at) }}</TableCell>
                <TableCell v-if="showProduct">
                    <Link v-if="m.product" :href="route('stock.products.show', m.product.id)" class="font-medium hover:underline">{{ m.product.name }}</Link>
                    <p class="text-xs text-muted-foreground">{{ m.product?.code }}</p>
                </TableCell>
                <TableCell v-if="showWarehouse" class="hidden text-muted-foreground md:table-cell">{{ m.warehouse?.name ?? '—' }}</TableCell>
                <TableCell><Badge :variant="variant('movementType', m.type)">{{ label('movementType', m.type) }}</Badge></TableCell>
                <TableCell class="text-right tabular-nums whitespace-nowrap">
                    {{ m.type === 'adjustment' ? '= ' : sign(m.type) }}{{ formatNumber(m.quantity) }}
                    <span v-if="m.product?.unit" class="text-xs text-muted-foreground">{{ m.product.unit }}</span>
                </TableCell>
                <TableCell class="hidden pr-4 text-muted-foreground lg:table-cell">
                    <p>{{ m.reference ?? '—' }}</p>
                    <p v-if="m.source_label" class="text-xs">{{ label('movementSource', m.source_label) }}</p>
                </TableCell>
            </TableRow>
        </TableBody>
    </Table>
</template>
