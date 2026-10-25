<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Check, ChevronRight, Pencil, Trash2 } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import ActivityList from '@/components/crm/ActivityList.vue';
import LoseDialog from './Partials/LoseDialog.vue';
import WinDialog from './Partials/WinDialog.vue';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription,
    AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { useAction } from '@/composables/useAction';
import { usePermissions } from '@/composables/usePermissions';
import { formatCurrency, formatDate, label, variant } from '@/composables/format';

const props = defineProps({
    opportunity: { type: Object, required: true },
    lead: { type: Object, default: null },
    wonQuote: { type: Object, default: null },
    stages: { type: Array, required: true },
    probabilities: { type: Object, required: true },
    activities: { type: Object, required: true },
    quotes: { type: Array, required: true },
});

const { can } = usePermissions();
// Computed: actions (advance, win…) reload props on this same page instance.
const o = computed(() => props.opportunity.data);
const isOpen = computed(() => props.stages.includes(o.value.stage));
const currentIndex = computed(() => props.stages.indexOf(o.value.stage));
const nextStage = computed(() => (isOpen.value ? props.stages[currentIndex.value + 1] ?? null : null));

// Stage tracker: open stages, then the outcome.
const steps = computed(() => [
    ...props.stages.map((stage, index) => ({
        key: stage,
        label: label('stage', stage),
        state: !isOpen.value || index < currentIndex.value ? 'done' : index === currentIndex.value ? 'current' : 'todo',
    })),
    {
        key: 'closed',
        label: isOpen.value ? 'Clôture' : label('stage', o.value.stage),
        state: isOpen.value ? 'todo' : o.value.stage === 'closed_won' ? 'won' : 'lost',
    },
]);

const { run, processing } = useAction();
const advance = () => run(route('crm.opportunities.advance', o.value.id));
const destroy = () => router.delete(route('crm.opportunities.destroy', o.value.id));
</script>

<template>
    <AppLayout :title="o.title" :breadcrumbs="[{ title: 'CRM' }, { title: 'Opportunités', href: route('crm.opportunities.index') }, { title: o.reference }]">
        <PageHeader :title="o.title" :description="o.reference">
            <template #actions>
                <Button v-if="nextStage && can('opportunities.advance')" variant="outline" :disabled="processing" @click="advance">
                    <ChevronRight /> {{ label('stage', nextStage) }}
                </Button>
                <WinDialog v-if="isOpen && can('opportunities.win')" :opportunity="o" :quotes="quotes" />
                <LoseDialog v-if="isOpen && can('opportunities.lose')" :opportunity="o" />
                <Button v-if="isOpen && can('opportunities.update')" variant="ghost" size="icon" as-child aria-label="Modifier">
                    <Link :href="route('crm.opportunities.edit', o.id)"><Pencil /></Link>
                </Button>
                <AlertDialog v-if="o.stage !== 'closed_won' && can('opportunities.delete')">
                    <AlertDialogTrigger as-child>
                        <Button variant="ghost" size="icon" aria-label="Supprimer"><Trash2 /></Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Supprimer cette opportunité ?</AlertDialogTitle>
                            <AlertDialogDescription>{{ o.reference }} · {{ o.title }}</AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Annuler</AlertDialogCancel>
                            <AlertDialogAction class="bg-destructive text-white hover:bg-destructive/90" @click="destroy">Supprimer</AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </template>
        </PageHeader>

        <Card class="py-4">
            <CardContent class="px-4">
                <ol class="grid grid-cols-5 gap-2" aria-label="Étapes">
                    <li v-for="step in steps" :key="step.key" class="grid gap-1.5">
                        <div class="h-1.5 rounded-full" :class="{
                            'bg-primary': step.state === 'done' || step.state === 'current' || step.state === 'won',
                            'bg-destructive': step.state === 'lost',
                            'bg-muted': step.state === 'todo',
                        }" />
                        <span class="flex items-center gap-1 truncate text-xs"
                            :class="step.state === 'current' ? 'font-medium' : 'text-muted-foreground'"
                            :aria-current="step.state === 'current' ? 'step' : undefined">
                            <Check v-if="step.state === 'done'" class="size-3 shrink-0" />{{ step.label }}
                        </span>
                    </li>
                </ol>
            </CardContent>
        </Card>

        <div class="grid gap-4 lg:grid-cols-3">
            <Card class="self-start">
                <CardHeader><CardTitle>Détails</CardTitle></CardHeader>
                <CardContent>
                    <dl class="grid gap-3 text-sm">
                        <div><dt class="text-muted-foreground">Étape</dt>
                            <dd><Badge :variant="variant('stage', o.stage)">{{ label('stage', o.stage) }}</Badge></dd></div>
                        <div><dt class="text-muted-foreground">Valeur</dt><dd class="font-medium tabular-nums">{{ formatCurrency(o.value) }}</dd></div>
                        <div><dt class="text-muted-foreground">Probabilité · valeur pondérée</dt>
                            <dd class="tabular-nums">{{ o.probability }} % · {{ formatCurrency(o.weighted_value) }}</dd></div>
                        <div><dt class="text-muted-foreground">{{ isOpen ? 'Clôture prévue' : 'Clôturée le' }}</dt>
                            <dd>{{ formatDate(isOpen ? o.expected_close_date : o.actual_close_date) }}</dd></div>
                        <div v-if="o.lost_reason"><dt class="text-muted-foreground">Raison de la perte</dt><dd class="whitespace-pre-line">{{ o.lost_reason }}</dd></div>
                        <div v-if="wonQuote"><dt class="text-muted-foreground">Devis accepté</dt><dd>{{ wonQuote.reference }}</dd></div>
                        <Separator />
                        <div><dt class="text-muted-foreground">Client</dt><dd class="font-medium">{{ o.customer?.name ?? '—' }}</dd></div>
                        <div><dt class="text-muted-foreground">Contact</dt>
                            <dd>
                                <Link v-if="o.contact" :href="route('crm.contacts.show', o.contact.id)" class="font-medium hover:underline">{{ o.contact.full_name }}</Link>
                                <span v-else>—</span>
                            </dd>
                        </div>
                        <div><dt class="text-muted-foreground">Responsable</dt><dd>{{ o.assignee?.name ?? 'Non assigné' }}</dd></div>
                        <div v-if="lead"><dt class="text-muted-foreground">Issue du lead</dt>
                            <dd><Link :href="route('crm.leads.show', lead.id)" class="hover:underline">{{ lead.reference }} · {{ lead.title }}</Link></dd>
                        </div>
                        <div v-if="o.description"><dt class="text-muted-foreground">Description</dt><dd class="whitespace-pre-line">{{ o.description }}</dd></div>
                    </dl>
                </CardContent>
            </Card>

            <div class="grid content-start gap-4 lg:col-span-2">
                <ActivityList :activities="activities.data" related-type="opportunity" :related-id="o.id" />
            </div>
        </div>
    </AppLayout>
</template>
