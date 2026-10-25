<script setup>
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatMoney } from '@/composables/format';

/**
 * Read-only lines and totals of a sales document. `document` carries
 * subtotal_ht, total_discount, total_tax, total_ttc and `lines`.
 * With `showProgress`, order lines also show delivered / invoiced quantities.
 */
defineProps({
    document: { type: Object, required: true },
    showProgress: { type: Boolean, default: false },
});

const qty = (value) => Number(value ?? 0);
</script>

<template>
    <Card class="gap-4">
        <CardHeader><CardTitle>Lignes</CardTitle></CardHeader>
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead class="pl-6">Description</TableHead>
                    <TableHead class="text-right">Qté</TableHead>
                    <TableHead v-if="showProgress" class="hidden text-right sm:table-cell">Livré</TableHead>
                    <TableHead v-if="showProgress" class="hidden text-right sm:table-cell">Facturé</TableHead>
                    <TableHead class="hidden text-right md:table-cell">P.U. HT</TableHead>
                    <TableHead class="hidden text-right lg:table-cell">Remise</TableHead>
                    <TableHead class="hidden text-right lg:table-cell">TVA</TableHead>
                    <TableHead class="pr-6 text-right">Total TTC</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="line in document.lines" :key="line.id">
                    <TableCell class="max-w-72 pl-6 whitespace-normal">
                        <p class="font-medium">{{ line.description }}</p>
                        <p v-if="line.product" class="text-xs text-muted-foreground">{{ line.product.code }}</p>
                    </TableCell>
                    <TableCell class="text-right tabular-nums">{{ qty(line.quantity) }}</TableCell>
                    <TableCell v-if="showProgress" class="hidden text-right tabular-nums sm:table-cell"
                        :class="qty(line.delivered_quantity) >= qty(line.quantity) ? '' : 'text-muted-foreground'">
                        {{ qty(line.delivered_quantity) }}
                    </TableCell>
                    <TableCell v-if="showProgress" class="hidden text-right tabular-nums sm:table-cell"
                        :class="qty(line.invoiced_quantity) >= qty(line.quantity) ? '' : 'text-muted-foreground'">
                        {{ qty(line.invoiced_quantity) }}
                    </TableCell>
                    <TableCell class="hidden text-right tabular-nums md:table-cell">{{ formatMoney(line.unit_price_ht) }}</TableCell>
                    <TableCell class="hidden text-right tabular-nums lg:table-cell">{{ qty(line.discount_value) ? `${qty(line.discount_value)} %` : '—' }}</TableCell>
                    <TableCell class="hidden text-right tabular-nums lg:table-cell">{{ qty(line.tax_rate) }} %</TableCell>
                    <TableCell class="pr-6 text-right font-medium tabular-nums">{{ formatMoney(line.total_ttc) }}</TableCell>
                </TableRow>
            </TableBody>
        </Table>
        <CardContent>
            <Separator class="mb-4" />
            <dl class="ml-auto grid w-full max-w-xs gap-1.5 text-sm">
                <div class="flex justify-between"><dt class="text-muted-foreground">Sous-total HT</dt><dd class="tabular-nums">{{ formatMoney(document.subtotal_ht) }}</dd></div>
                <div v-if="qty(document.total_discount)" class="flex justify-between"><dt class="text-muted-foreground">Remises</dt><dd class="tabular-nums">− {{ formatMoney(document.total_discount) }}</dd></div>
                <div class="flex justify-between"><dt class="text-muted-foreground">TVA</dt><dd class="tabular-nums">{{ formatMoney(document.total_tax) }}</dd></div>
                <div class="flex justify-between border-t pt-1.5 text-base font-semibold"><dt>Total TTC</dt><dd class="tabular-nums">{{ formatMoney(document.total_ttc) }}</dd></div>
                <slot name="after-totals" />
            </dl>
        </CardContent>
    </Card>
</template>
