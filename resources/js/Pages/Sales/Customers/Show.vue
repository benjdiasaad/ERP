<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { FilePlus2, Mail, MapPin, Pencil, Phone, Trash2 } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription,
    AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Separator } from '@/components/ui/separator';
import { usePermissions } from '@/composables/usePermissions';
import { formatCurrency, formatDate, label, variant } from '@/composables/format';

const props = defineProps({
    customer: { type: Object, required: true },
    credit: { type: Object, required: true },
    quotes: { type: Array, default: null },
    contacts: { type: Array, default: null },
    opportunities: { type: Array, default: null },
});

const { can } = usePermissions();
const c = computed(() => props.customer.data);
const creditUsed = computed(() => (props.credit.has_credit_limit && props.credit.credit_limit > 0
    ? Math.min(100, (props.credit.balance / props.credit.credit_limit) * 100) : 0));

const destroy = () => router.delete(route('sales.customers.destroy', c.value.id));
</script>

<template>
    <AppLayout :title="c.name" :breadcrumbs="[{ title: 'Ventes' }, { title: 'Clients', href: route('sales.customers.index') }, { title: c.name }]">
        <PageHeader :title="c.name" :description="`${c.code} · ${label('customerType', c.type)}`">
            <template #actions>
                <Button v-if="can('quotes.create')" as-child>
                    <Link :href="route('sales.quotes.create', { customer_id: c.id })"><FilePlus2 /> Nouveau devis</Link>
                </Button>
                <Button v-if="can('customers.update')" variant="outline" as-child>
                    <Link :href="route('sales.customers.edit', c.id)"><Pencil /> Modifier</Link>
                </Button>
                <AlertDialog v-if="can('customers.delete')">
                    <AlertDialogTrigger as-child>
                        <Button variant="ghost" size="icon" aria-label="Supprimer"><Trash2 /></Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Supprimer {{ c.name }} ?</AlertDialogTitle>
                            <AlertDialogDescription>Le client sera retiré des listes. Ses devis et factures sont conservés.</AlertDialogDescription>
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
            <div class="grid content-start gap-4">
                <Card>
                    <CardHeader><CardTitle>Coordonnées</CardTitle></CardHeader>
                    <CardContent>
                        <dl class="grid gap-3 text-sm">
                            <div class="flex min-w-0 items-center gap-2">
                                <Mail class="size-4 shrink-0 text-muted-foreground" />
                                <a v-if="c.email" :href="`mailto:${c.email}`" class="truncate hover:underline">{{ c.email }}</a><span v-else class="text-muted-foreground">—</span>
                            </div>
                            <div class="flex items-center gap-2"><Phone class="size-4 shrink-0 text-muted-foreground" />{{ c.phone ?? c.mobile ?? '—' }}</div>
                            <div class="flex items-start gap-2">
                                <MapPin class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                <span>{{ [c.address, [c.postal_code, c.city].filter(Boolean).join(' '), c.country].filter(Boolean).join(', ') || '—' }}</span>
                            </div>
                            <template v-if="c.type === 'company'">
                                <Separator />
                                <div class="grid grid-cols-3 gap-2">
                                    <div><dt class="text-muted-foreground">ICE</dt><dd class="break-all">{{ c.ice ?? '—' }}</dd></div>
                                    <div><dt class="text-muted-foreground">RC</dt><dd>{{ c.rc ?? '—' }}</dd></div>
                                    <div><dt class="text-muted-foreground">IF</dt><dd>{{ c.tax_id ?? '—' }}</dd></div>
                                </div>
                            </template>
                            <Separator />
                            <div><dt class="text-muted-foreground">Conditions de paiement</dt><dd>{{ c.payment_term?.name ?? '—' }}</dd></div>
                            <div><dt class="text-muted-foreground">Statut</dt>
                                <dd><Badge :variant="c.is_active ? 'secondary' : 'outline'">{{ c.is_active ? 'Actif' : 'Inactif' }}</Badge></dd></div>
                            <div v-if="c.notes"><dt class="text-muted-foreground">Notes</dt><dd class="whitespace-pre-line">{{ c.notes }}</dd></div>
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Encours</CardTitle>
                        <CardDescription v-if="credit.has_credit_limit">Plafond {{ formatCurrency(credit.credit_limit) }}</CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-3 text-sm">
                        <p class="text-2xl font-semibold tabular-nums" :class="credit.is_over_limit ? 'text-destructive' : ''">{{ formatCurrency(credit.balance) }}</p>
                        <template v-if="credit.has_credit_limit">
                            <Progress :model-value="creditUsed" />
                            <p class="text-muted-foreground">
                                {{ credit.is_over_limit ? 'Plafond dépassé.' : `${formatCurrency(credit.available_credit)} disponibles.` }}
                            </p>
                        </template>
                        <p v-else class="text-muted-foreground">Aucun plafond de crédit.</p>
                    </CardContent>
                </Card>
            </div>

            <div class="grid content-start gap-4 lg:col-span-2">
                <Card v-if="quotes">
                    <CardHeader>
                        <CardTitle>Devis récents</CardTitle>
                        <CardAction>
                            <Button variant="link" size="sm" as-child class="px-0">
                                <Link :href="route('sales.quotes.index', { customer_id: c.id })">Tout voir</Link>
                            </Button>
                        </CardAction>
                    </CardHeader>
                    <CardContent>
                        <ul v-if="quotes.length" class="grid gap-3">
                            <li v-for="q in quotes" :key="q.id" class="flex items-center justify-between gap-3 text-sm">
                                <div class="min-w-0">
                                    <Link :href="route('sales.quotes.show', q.id)" class="font-medium hover:underline">{{ q.reference }}</Link>
                                    <p class="text-xs text-muted-foreground">{{ formatDate(q.quote_date) }}</p>
                                </div>
                                <div class="flex shrink-0 items-center gap-3">
                                    <span class="tabular-nums">{{ formatCurrency(q.total_ttc) }}</span>
                                    <Badge :variant="variant('quoteStatus', q.status)">{{ label('quoteStatus', q.status) }}</Badge>
                                </div>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-muted-foreground">Aucun devis.</p>
                    </CardContent>
                </Card>

                <Card v-if="opportunities">
                    <CardHeader><CardTitle>Opportunités</CardTitle></CardHeader>
                    <CardContent>
                        <ul v-if="opportunities.length" class="grid gap-3">
                            <li v-for="o in opportunities" :key="o.id" class="flex items-center justify-between gap-3 text-sm">
                                <div class="min-w-0">
                                    <Link :href="route('crm.opportunities.show', o.id)" class="block truncate font-medium hover:underline">{{ o.title }}</Link>
                                    <p class="text-xs text-muted-foreground">{{ o.reference }}</p>
                                </div>
                                <div class="flex shrink-0 items-center gap-3">
                                    <span class="tabular-nums text-muted-foreground">{{ formatCurrency(o.value) }}</span>
                                    <Badge :variant="variant('stage', o.stage)">{{ label('stage', o.stage) }}</Badge>
                                </div>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-muted-foreground">Aucune opportunité.</p>
                    </CardContent>
                </Card>

                <Card v-if="contacts">
                    <CardHeader><CardTitle>Contacts</CardTitle></CardHeader>
                    <CardContent>
                        <ul v-if="contacts.length" class="grid gap-3">
                            <li v-for="ct in contacts" :key="ct.id" class="flex items-center justify-between gap-3 text-sm">
                                <div class="min-w-0">
                                    <Link :href="route('crm.contacts.show', ct.id)" class="font-medium hover:underline">{{ ct.full_name }}</Link>
                                    <p class="text-xs text-muted-foreground">{{ ct.job_title ?? '—' }}</p>
                                </div>
                                <span class="truncate text-muted-foreground">{{ ct.email ?? ct.phone ?? '' }}</span>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-muted-foreground">Aucun contact lié.</p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
