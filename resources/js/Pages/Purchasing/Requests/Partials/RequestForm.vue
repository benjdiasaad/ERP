<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { formatMoney, labels } from '@/composables/format';

const props = defineProps({
    form: { type: Object, required: true },
    suppliers: { type: Array, required: true },
    submitLabel: { type: String, required: true },
    cancelHref: { type: String, required: true },
});

defineEmits(['submit']);

const priorities = Object.entries(labels.priority).map(([value, label]) => ({ value, label }));
const lineTotal = (line) => (Number(line.quantity) || 0) * (Number(line.estimated_unit_price) || 0);
const total = computed(() => props.form.lines.reduce((sum, line) => sum + lineTotal(line), 0));

const addLine = () => props.form.lines.push({ description: '', quantity: 1, unit: '', estimated_unit_price: 0 });
const removeLine = (index) => props.form.lines.splice(index, 1);
const lineError = (index, field) => props.form.errors[`lines.${index}.${field}`];
</script>

<template>
    <form class="grid gap-6" @submit.prevent="$emit('submit')">
        <Card>
            <CardHeader><CardTitle>Demande</CardTitle></CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-3">
                <FormField id="title" v-model="form.title" label="Objet" required class="sm:col-span-3" placeholder="Ex. : Renouvellement des postes de la comptabilité" :error="form.errors.title" />
                <FormField id="priority" v-model="form.priority" label="Priorité" :options="priorities" :error="form.errors.priority" />
                <FormField id="required_date" v-model="form.required_date" label="Souhaité pour le" type="date" :error="form.errors.required_date" />
                <FormField id="supplier_id" v-model="form.supplier_id" label="Fournisseur suggéré" :options="suppliers" placeholder="À choisir par les achats" :error="form.errors.supplier_id" />
                <FormField id="description" v-model="form.description" label="Justification" type="textarea" class="sm:col-span-3" :error="form.errors.description" />
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Articles demandés</CardTitle>
                <CardAction><Button type="button" variant="outline" size="sm" @click="addLine"><Plus /> Ajouter un article</Button></CardAction>
            </CardHeader>
            <CardContent class="grid gap-4">
                <p v-if="form.errors.lines" class="text-sm text-destructive">{{ form.errors.lines }}</p>
                <div v-for="(line, index) in form.lines" :key="index" class="grid gap-3 rounded-lg border p-3 sm:grid-cols-[minmax(0,1fr)_6rem_6rem_8rem_7rem_auto] sm:items-end">
                    <div class="grid gap-2">
                        <Label :for="`pr-${index}-description`">Article<span class="text-destructive">*</span></Label>
                        <Input :id="`pr-${index}-description`" v-model="line.description" :aria-invalid="!!lineError(index, 'description') || undefined" />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="`pr-${index}-quantity`">Quantité</Label>
                        <Input :id="`pr-${index}-quantity`" v-model="line.quantity" type="number" min="0" step="0.01" />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="`pr-${index}-unit`">Unité</Label>
                        <Input :id="`pr-${index}-unit`" v-model="line.unit" />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="`pr-${index}-price`">Prix estimé HT</Label>
                        <Input :id="`pr-${index}-price`" v-model="line.estimated_unit_price" type="number" min="0" step="0.01" />
                    </div>
                    <div class="grid gap-2 text-right">
                        <span class="text-sm font-medium">Total</span>
                        <span class="h-9 content-center tabular-nums">{{ formatMoney(lineTotal(line)) }}</span>
                    </div>
                    <Button type="button" variant="ghost" size="icon" :disabled="form.lines.length === 1" :aria-label="`Supprimer l'article ${index + 1}`" @click="removeLine(index)">
                        <Trash2 />
                    </Button>
                </div>
                <Separator />
                <p class="text-right text-sm">Estimation totale HT : <span class="font-semibold tabular-nums">{{ formatMoney(total) }}</span></p>
            </CardContent>
        </Card>

        <div class="flex justify-end gap-2">
            <Button variant="outline" as-child><Link :href="cancelHref">Annuler</Link></Button>
            <Button type="submit" :disabled="form.processing">{{ submitLabel }}</Button>
        </div>
    </form>
</template>
