<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription,
    AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { usePermissions } from '@/composables/usePermissions';
import { formatAmount, formatDate } from '@/composables/format';

const props = defineProps({
    account: { type: Object, required: true },
});

const { can } = usePermissions();
const a = computed(() => props.account.data);
const destroy = () => router.delete(route('finance.bank-accounts.destroy', a.value.id));
</script>

<template>
    <AppLayout :title="a.name" :breadcrumbs="[{ title: 'Finance' }, { title: 'Comptes bancaires', href: route('finance.bank-accounts.index') }, { title: a.name }]">
        <PageHeader :title="a.name" :description="a.bank">
            <template #actions>
                <Badge :variant="a.is_active ? 'secondary' : 'outline'">{{ a.is_active ? 'Actif' : 'Inactif' }}</Badge>
                <Button v-if="can('bank_accounts.update')" variant="outline" as-child>
                    <Link :href="route('finance.bank-accounts.edit', a.id)"><Pencil /> Modifier</Link>
                </Button>
                <AlertDialog v-if="can('bank_accounts.delete')">
                    <AlertDialogTrigger as-child>
                        <Button variant="ghost" size="icon" aria-label="Supprimer"><Trash2 /></Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Supprimer {{ a.name }} ?</AlertDialogTitle>
                            <AlertDialogDescription>Le compte sera retiré des listes. Préférez le désactiver s'il a servi à des paiements.</AlertDialogDescription>
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
            <Card class="gap-1">
                <CardHeader>
                    <CardDescription>Solde</CardDescription>
                    <CardTitle class="text-3xl tabular-nums">{{ formatAmount(a.balance, a.currency?.code) }}</CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-muted-foreground">Mis à jour le {{ formatDate(a.updated_at) }}</CardContent>
            </Card>

            <Card class="lg:col-span-2">
                <CardHeader><CardTitle>Coordonnées bancaires</CardTitle></CardHeader>
                <CardContent>
                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div><dt class="text-muted-foreground">Banque</dt><dd>{{ a.bank }}</dd></div>
                        <div><dt class="text-muted-foreground">Devise</dt><dd>{{ a.currency?.code ?? '—' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-muted-foreground">N° de compte / RIB</dt><dd class="font-mono break-all">{{ a.account_number }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-muted-foreground">IBAN</dt><dd class="font-mono break-all">{{ a.iban ?? '—' }}</dd></div>
                        <div><dt class="text-muted-foreground">SWIFT / BIC</dt><dd class="font-mono">{{ a.swift ?? '—' }}</dd></div>
                    </dl>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
