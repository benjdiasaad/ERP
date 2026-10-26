<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { CircleAlert, CircleCheck, Plus, Trash2 } from '@lucide/vue';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Table, TableBody, TableCell, TableFooter, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatAmount } from '@/composables/format';

const props = defineProps({
    form: { type: Object, required: true },
    accounts: { type: Array, required: true },
    submitLabel: { type: String, required: true },
    cancelHref: { type: String, required: true },
});

defineEmits(['submit']);

const addLine = () => {
    // Pre-fill the new line with what balances the entry.
    const gap = Math.round((totals.value.debit - totals.value.credit) * 100) / 100;
    props.form.lines.push({ chart_of_account_id: null, description: '', debit: gap < 0 ? -gap : 0, credit: gap > 0 ? gap : 0 });
};
const removeLine = (index) => props.form.lines.splice(index, 1);

// A line is either a debit or a credit: typing one clears the other.
const setAmount = (line, side, value) => {
    line[side] = value;
    if (Number(value) > 0) line[side === 'debit' ? 'credit' : 'debit'] = 0;
};

const totals = computed(() => props.form.lines.reduce((sum, line) => ({
    debit: sum.debit + Number(line.debit || 0),
    credit: sum.credit + Number(line.credit || 0),
}), { debit: 0, credit: 0 }));
const difference = computed(() => Math.round((totals.value.debit - totals.value.credit) * 100) / 100);
const lineError = (index, field) => props.form.errors[`lines.${index}.${field}`];
</script>

<template>
    <form class="grid gap-6" @submit.prevent="$emit('submit')">
        <Card>
            <CardHeader><CardTitle>Écriture</CardTitle></CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-3">
                <FormField id="date" v-model="form.date" label="Date" type="date" required :error="form.errors.date" />
                <FormField id="reference" v-model="form.reference" label="Référence" placeholder="Générée automatiquement" :error="form.errors.reference" />
                <FormField id="description" v-model="form.description" label="Libellé" placeholder="Ex. : Règlement facture FA-2026-00012" class="sm:col-span-3" :error="form.errors.description" />
            </CardContent>
        </Card>

        <Card class="gap-4 pb-2">
            <CardHeader>
                <CardTitle>Lignes</CardTitle>
                <CardDescription>Le total des débits doit égaler le total des crédits pour comptabiliser l'écriture.</CardDescription>
                <CardAction><Button type="button" variant="outline" size="sm" @click="addLine"><Plus /> Ajouter une ligne</Button></CardAction>
            </CardHeader>
            <p v-if="form.errors.lines" class="px-6 text-sm text-destructive">{{ form.errors.lines }}</p>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="min-w-56 pl-4">Compte</TableHead>
                        <TableHead class="min-w-40">Libellé</TableHead>
                        <TableHead class="w-36 text-right">Débit</TableHead>
                        <TableHead class="w-36 text-right">Crédit</TableHead>
                        <TableHead class="w-12 pr-4"><span class="sr-only">Retirer</span></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="(line, index) in form.lines" :key="index" class="align-top">
                        <TableCell class="pl-4">
                            <NativeSelect v-model="line.chart_of_account_id" class="w-full" :aria-label="`Compte ligne ${index + 1}`"
                                :aria-invalid="!!lineError(index, 'chart_of_account_id') || undefined">
                                <option :value="null">Choisir un compte…</option>
                                <option v-for="a in accounts" :key="a.value" :value="a.value">{{ a.label }}</option>
                            </NativeSelect>
                            <p v-if="lineError(index, 'chart_of_account_id')" class="mt-1 text-xs text-destructive">{{ lineError(index, 'chart_of_account_id') }}</p>
                        </TableCell>
                        <TableCell><Input v-model="line.description" :aria-label="`Libellé ligne ${index + 1}`" /></TableCell>
                        <TableCell>
                            <Input :model-value="line.debit" type="number" min="0" step="0.01" class="text-right" :aria-label="`Débit ligne ${index + 1}`"
                                @update:model-value="setAmount(line, 'debit', $event)" />
                        </TableCell>
                        <TableCell>
                            <Input :model-value="line.credit" type="number" min="0" step="0.01" class="text-right" :aria-label="`Crédit ligne ${index + 1}`"
                                @update:model-value="setAmount(line, 'credit', $event)" />
                        </TableCell>
                        <TableCell class="pr-4">
                            <Button type="button" variant="ghost" size="icon" :aria-label="`Retirer la ligne ${index + 1}`" @click="removeLine(index)"><Trash2 /></Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
                <TableFooter>
                    <TableRow>
                        <TableCell colspan="2" class="pl-4">
                            <span v-if="difference === 0 && totals.debit > 0" class="inline-flex items-center gap-1.5 text-emerald-700 dark:text-emerald-400"><CircleCheck class="size-4" /> Écriture équilibrée</span>
                            <span v-else-if="difference !== 0" class="inline-flex items-center gap-1.5 text-destructive"><CircleAlert class="size-4" /> Écart : {{ formatAmount(Math.abs(difference)) }}</span>
                        </TableCell>
                        <TableCell class="text-right tabular-nums">{{ formatAmount(totals.debit) }}</TableCell>
                        <TableCell class="text-right tabular-nums">{{ formatAmount(totals.credit) }}</TableCell>
                        <TableCell />
                    </TableRow>
                </TableFooter>
            </Table>
        </Card>

        <div class="flex justify-end gap-2">
            <Button variant="outline" as-child><Link :href="cancelHref">Annuler</Link></Button>
            <Button type="submit" :disabled="form.processing">{{ submitLabel }}</Button>
        </div>
    </form>
</template>
