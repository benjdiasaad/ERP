<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Landmark, Mail, MapPin, Pencil, Phone, ShoppingCart, Trash2 } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription,
    AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { usePermissions } from '@/composables/usePermissions';
import { formatDate, formatMoney, label, variant } from '@/composables/format';

const props = defineProps({
    supplier: { type: Object, required: true },
    orders: { type: Array, default: null },
    invoices: { type: Array, default: null },
});

const { can } = usePermissions();
const s = computed(() => props.supplier.data);
const destroy = () => router.delete(route('purchasing.suppliers.destroy', s.value.id));
</script>

<template>
    <AppLayout :title="s.name" :breadcrumbs="[{ title: 'Achats' }, { title: 'Fournisseurs', href: route('purchasing.suppliers.index') }, { title: s.name }]">
        <PageHeader :title="s.name" :description="`${s.code} · ${label('supplierType', s.type)}`">
            <template #actions>
                <Button v-if="can('purchase_orders.create')" as-child>
                    <Link :href="route('purchasing.orders.create', { supplier_id: s.id })"><ShoppingCart /> Nouveau bon de commande</Link>
                </Button>
                <Button v-if="can('suppliers.update')" variant="outline" as-child>
                    <Link :href="route('purchasing.suppliers.edit', s.id)"><Pencil /> Modifier</Link>
                </Button>
                <AlertDialog v-if="can('suppliers.delete')">
                    <AlertDialogTrigger as-child>
                        <Button variant="ghost" size="icon" aria-label="Supprimer"><Trash2 /></Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Supprimer {{ s.name }} ?</AlertDialogTitle>
                            <AlertDialogDescription>Le fournisseur sera retiré des listes. Ses documents sont conservés.</AlertDialogDescription>
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
                <CardHeader><CardTitle>Coordonnées</CardTitle></CardHeader>
                <CardContent>
                    <dl class="grid gap-3 text-sm">
                        <div class="flex min-w-0 items-center gap-2"><Mail class="size-4 shrink-0 text-muted-foreground" />
                            <a v-if="s.email" :href="`mailto:${s.email}`" class="truncate hover:underline">{{ s.email }}</a><span v-else class="text-muted-foreground">—</span>
                        </div>
                        <div class="flex items-center gap-2"><Phone class="size-4 shrink-0 text-muted-foreground" />{{ s.phone ?? s.mobile ?? '—' }}</div>
                        <div class="flex items-start gap-2"><MapPin class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                            <span>{{ [s.address, [s.postal_code, s.city].filter(Boolean).join(' '), s.country].filter(Boolean).join(', ') || '—' }}</span>
                        </div>
                        <template v-if="s.type === 'company'">
                            <Separator />
                            <div class="grid grid-cols-3 gap-2">
                                <div><dt class="text-muted-foreground">ICE</dt><dd class="break-all">{{ s.ice ?? '—' }}</dd></div>
                                <div><dt class="text-muted-foreground">RC</dt><dd>{{ s.rc ?? '—' }}</dd></div>
                                <div><dt class="text-muted-foreground">IF</dt><dd>{{ s.tax_id ?? '—' }}</dd></div>
                            </div>
                        </template>
                        <Separator />
                        <div class="flex items-start gap-2"><Landmark class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                            <div class="min-w-0">
                                <p>{{ s.bank_name ?? 'Banque non renseignée' }}</p>
                                <p v-if="s.bank_account_number" class="break-all text-muted-foreground">{{ s.bank_account_number }}</p>
                                <p v-if="s.bank_iban" class="break-all text-xs text-muted-foreground">IBAN {{ s.bank_iban }}</p>
                            </div>
                        </div>
                        <div><dt class="text-muted-foreground">Conditions de paiement</dt><dd>{{ s.payment_term?.name ?? '—' }}</dd></div>
                        <div><dt class="text-muted-foreground">Solde dû</dt><dd class="font-medium tabular-nums">{{ formatMoney(s.balance) }}</dd></div>
                        <div><dt class="text-muted-foreground">Statut</dt><dd><Badge :variant="s.is_active ? 'secondary' : 'outline'">{{ s.is_active ? 'Actif' : 'Inactif' }}</Badge></dd></div>
                        <div v-if="s.notes"><dt class="text-muted-foreground">Notes</dt><dd class="whitespace-pre-line">{{ s.notes }}</dd></div>
                    </dl>
                </CardContent>
            </Card>

            <div class="grid content-start gap-4 lg:col-span-2">
                <Card v-if="orders">
                    <CardHeader><CardTitle>Bons de commande récents</CardTitle></CardHeader>
                    <CardContent>
                        <ul v-if="orders.length" class="grid gap-3">
                            <li v-for="o in orders" :key="o.id" class="flex items-center justify-between gap-3 text-sm">
                                <div><Link :href="route('purchasing.orders.show', o.id)" class="font-medium hover:underline">{{ o.reference }}</Link>
                                    <p class="text-xs text-muted-foreground">{{ formatDate(o.order_date) }}</p></div>
                                <div class="flex items-center gap-3"><span class="tabular-nums">{{ formatMoney(o.total_ttc) }}</span>
                                    <Badge :variant="variant('purchaseOrderStatus', o.status)">{{ label('purchaseOrderStatus', o.status) }}</Badge></div>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-muted-foreground">Aucun bon de commande.</p>
                    </CardContent>
                </Card>

                <Card v-if="invoices">
                    <CardHeader><CardTitle>Factures récentes</CardTitle></CardHeader>
                    <CardContent>
                        <ul v-if="invoices.length" class="grid gap-3">
                            <li v-for="i in invoices" :key="i.id" class="flex items-center justify-between gap-3 text-sm">
                                <div><Link :href="route('purchasing.invoices.show', i.id)" class="font-medium hover:underline">{{ i.reference }}</Link>
                                    <p class="text-xs text-muted-foreground">{{ formatDate(i.invoice_date) }}</p></div>
                                <div class="flex items-center gap-3"><span class="tabular-nums">{{ formatMoney(i.total_ttc) }}</span>
                                    <Badge :variant="variant('invoiceStatus', i.status)">{{ label('invoiceStatus', i.status) }}</Badge></div>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-muted-foreground">Aucune facture.</p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
