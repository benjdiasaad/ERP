<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Ban, BookCheck, Pencil, Trash2 } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import ReasonDialog from '@/components/ReasonDialog.vue';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription,
    AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableFooter, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useAction } from '@/composables/useAction';
import { usePermissions } from '@/composables/usePermissions';
import { formatAmount, formatDate, formatDateTime, label, variant } from '@/composables/format';

const props = defineProps({
    entry: { type: Object, required: true },
});

const { can } = usePermissions();
// Computed: workflow actions reload props on this same page instance.
const e = computed(() => props.entry.data);
const balanced = computed(() => e.value.lines.length > 0 && Math.abs(e.value.total_debit - e.value.total_credit) < 0.005);

const { run, processing } = useAction();
const post = () => run(route('finance.journal-entries.post', e.value.id));
const destroy = () => router.delete(route('finance.journal-entries.destroy', e.value.id));
</script>

<template>
    <AppLayout :title="e.reference" :breadcrumbs="[{ title: 'Finance' }, { title: 'Écritures', href: route('finance.journal-entries.index') }, { title: e.reference }]">
        <PageHeader :title="e.reference" :description="`${formatDate(e.date)}${e.description ? ' · ' + e.description : ''}`">
            <template #actions>
                <Badge :variant="variant('journalStatus', e.status)">{{ label('journalStatus', e.status) }}</Badge>
                <AlertDialog v-if="e.status === 'draft' && can('journal_entries.post')">
                    <AlertDialogTrigger as-child>
                        <Button :disabled="processing || !balanced"><BookCheck /> Comptabiliser</Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Comptabiliser l'écriture {{ e.reference }} ?</AlertDialogTitle>
                            <AlertDialogDescription>Une fois comptabilisée, l'écriture ne peut plus être modifiée ni supprimée.</AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Annuler</AlertDialogCancel>
                            <AlertDialogAction @click="post">Comptabiliser</AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
                <Button v-if="e.status === 'draft' && can('journal_entries.update')" variant="outline" as-child>
                    <Link :href="route('finance.journal-entries.edit', e.id)"><Pencil /> Modifier</Link>
                </Button>
                <ReasonDialog v-if="e.status === 'draft' && can('journal_entries.cancel')" :url="route('finance.journal-entries.cancel', e.id)"
                    title="Annuler l'écriture" :description="e.reference" confirm-label="Annuler l'écriture" trigger-variant="ghost">
                    <Ban /> Annuler
                </ReasonDialog>
                <AlertDialog v-if="e.status === 'draft' && can('journal_entries.delete')">
                    <AlertDialogTrigger as-child>
                        <Button variant="ghost" size="icon" aria-label="Supprimer"><Trash2 /></Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Supprimer l'écriture {{ e.reference }} ?</AlertDialogTitle>
                            <AlertDialogDescription>Le brouillon sera supprimé.</AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Annuler</AlertDialogCancel>
                            <AlertDialogAction class="bg-destructive text-white hover:bg-destructive/90" @click="destroy">Supprimer</AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </template>
        </PageHeader>

        <p v-if="e.status === 'draft' && !balanced" class="text-sm text-destructive">
            L'écriture n'est pas équilibrée (débit {{ formatAmount(e.total_debit) }}, crédit {{ formatAmount(e.total_credit) }}) : modifiez-la avant de la comptabiliser.
        </p>

        <Card class="gap-4 pb-2">
            <CardHeader><CardTitle>Lignes</CardTitle></CardHeader>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Compte</TableHead>
                        <TableHead class="hidden sm:table-cell">Libellé</TableHead>
                        <TableHead class="text-right">Débit</TableHead>
                        <TableHead class="pr-4 text-right">Crédit</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="line in e.lines" :key="line.id">
                        <TableCell class="pl-4">
                            <span class="font-mono">{{ line.chart_of_account?.code }}</span>
                            <span class="ml-2">{{ line.chart_of_account?.name }}</span>
                            <p v-if="line.description" class="text-xs text-muted-foreground sm:hidden">{{ line.description }}</p>
                        </TableCell>
                        <TableCell class="hidden text-muted-foreground sm:table-cell">{{ line.description ?? '—' }}</TableCell>
                        <TableCell class="text-right tabular-nums">{{ line.debit ? formatAmount(line.debit) : '' }}</TableCell>
                        <TableCell class="pr-4 text-right tabular-nums">{{ line.credit ? formatAmount(line.credit) : '' }}</TableCell>
                    </TableRow>
                    <TableRow v-if="!e.lines.length">
                        <TableCell colspan="4" class="pl-4 text-muted-foreground">Aucune ligne.</TableCell>
                    </TableRow>
                </TableBody>
                <TableFooter>
                    <TableRow>
                        <TableCell colspan="2" class="pl-4 font-medium">Total</TableCell>
                        <TableCell class="text-right font-medium tabular-nums">{{ formatAmount(e.total_debit) }}</TableCell>
                        <TableCell class="pr-4 text-right font-medium tabular-nums">{{ formatAmount(e.total_credit) }}</TableCell>
                    </TableRow>
                </TableFooter>
            </Table>
        </Card>

        <Card v-if="e.status !== 'draft'">
            <CardHeader><CardTitle>Suivi</CardTitle></CardHeader>
            <CardContent>
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <template v-if="e.status === 'posted'">
                        <div><dt class="text-muted-foreground">Comptabilisée le</dt><dd>{{ formatDateTime(e.posted_at) }}</dd></div>
                        <div><dt class="text-muted-foreground">Par</dt><dd>{{ e.posted_by_user?.name ?? '—' }}</dd></div>
                    </template>
                    <template v-else>
                        <div><dt class="text-muted-foreground">Annulée le</dt><dd>{{ formatDateTime(e.cancelled_at) }}</dd></div>
                        <div><dt class="text-muted-foreground">Motif</dt><dd class="whitespace-pre-line">{{ e.cancellation_reason ?? '—' }}</dd></div>
                    </template>
                </dl>
            </CardContent>
        </Card>
    </AppLayout>
</template>
