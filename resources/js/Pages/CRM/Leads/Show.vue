<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { BadgeCheck, Pencil, Trash2 } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import ActivityList from '@/components/crm/ActivityList.vue';
import ConvertLeadDialog from './Partials/ConvertLeadDialog.vue';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription,
    AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { NativeSelect } from '@/components/ui/native-select';
import { Separator } from '@/components/ui/separator';
import { useAction } from '@/composables/useAction';
import { usePermissions } from '@/composables/usePermissions';
import { formatCurrency, formatDate, formatDateTime, label, variant } from '@/composables/format';

const props = defineProps({
    lead: { type: Object, required: true },
    opportunity: { type: Object, default: null },
    activities: { type: Object, required: true },
    users: { type: Array, required: true },
    customers: { type: Array, required: true },
});

const { can } = usePermissions();
// Computed: actions (qualify, assign) reload props on this same page instance.
const l = computed(() => props.lead.data);
const isOpen = computed(() => ['new', 'contacted', 'qualified', 'unqualified'].includes(l.value.status));
const canQualify = computed(() => ['new', 'contacted', 'unqualified'].includes(l.value.status));

const { run, processing } = useAction();
const qualify = () => run(route('crm.leads.qualify', l.value.id));

const assignee = ref(l.value.assigned_to);
watch(() => l.value.assigned_to, (value) => (assignee.value = value));
const assign = () => run(route('crm.leads.assign', l.value.id), { assigned_to: assignee.value });

const destroy = () => router.delete(route('crm.leads.destroy', l.value.id));
</script>

<template>
    <AppLayout :title="l.title" :breadcrumbs="[{ title: 'CRM' }, { title: 'Leads', href: route('crm.leads.index') }, { title: l.reference }]">
        <PageHeader :title="l.title" :description="`${l.reference} · créé le ${formatDate(l.created_at)}`">
            <template #actions>
                <Button v-if="canQualify && can('leads.qualify')" variant="outline" :disabled="processing" @click="qualify">
                    <BadgeCheck /> Qualifier
                </Button>
                <ConvertLeadDialog v-if="l.status === 'qualified' && can('leads.convert')" :lead="l" :customers="customers"
                    :default-customer-id="l.contact?.customer_id ?? null" />
                <Button v-if="isOpen && can('leads.update')" variant="outline" as-child>
                    <Link :href="route('crm.leads.edit', l.id)"><Pencil /> Modifier</Link>
                </Button>
                <AlertDialog v-if="l.status !== 'converted' && can('leads.delete')">
                    <AlertDialogTrigger as-child>
                        <Button variant="ghost" size="icon" aria-label="Supprimer"><Trash2 /></Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Supprimer ce lead ?</AlertDialogTitle>
                            <AlertDialogDescription>{{ l.reference }} · {{ l.title }}</AlertDialogDescription>
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
                    <CardHeader><CardTitle>Détails</CardTitle></CardHeader>
                    <CardContent>
                        <dl class="grid gap-3 text-sm">
                            <div><dt class="text-muted-foreground">Statut</dt>
                                <dd><Badge :variant="variant('leadStatus', l.status)">{{ label('leadStatus', l.status) }}</Badge></dd></div>
                            <div><dt class="text-muted-foreground">Valeur estimée</dt><dd class="font-medium tabular-nums">{{ l.estimated_value ? formatCurrency(l.estimated_value) : '—' }}</dd></div>
                            <div><dt class="text-muted-foreground">Probabilité</dt><dd>{{ l.probability != null ? `${l.probability} %` : '—' }}</dd></div>
                            <div><dt class="text-muted-foreground">Clôture prévue</dt><dd>{{ formatDate(l.expected_close_date) }}</dd></div>
                            <div><dt class="text-muted-foreground">Source</dt><dd>{{ label('source', l.source) }}</dd></div>
                            <Separator />
                            <div><dt class="text-muted-foreground">Contact</dt>
                                <dd>
                                    <Link v-if="l.contact" :href="route('crm.contacts.show', l.contact.id)" class="font-medium hover:underline">{{ l.contact.full_name }}</Link>
                                    <span v-else>—</span>
                                    <p v-if="l.contact?.organization" class="text-xs text-muted-foreground">{{ l.contact.organization }}</p>
                                </dd>
                            </div>
                            <div v-if="l.qualified_at"><dt class="text-muted-foreground">Qualifié le</dt><dd>{{ formatDateTime(l.qualified_at) }}</dd></div>
                            <div v-if="l.lost_reason"><dt class="text-muted-foreground">Raison de la perte</dt><dd class="whitespace-pre-line">{{ l.lost_reason }}</dd></div>
                            <div v-if="l.description"><dt class="text-muted-foreground">Description</dt><dd class="whitespace-pre-line">{{ l.description }}</dd></div>
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Responsable</CardTitle></CardHeader>
                    <CardContent>
                        <form v-if="isOpen && can('leads.assign')" class="grid gap-2" @submit.prevent="assign">
                            <NativeSelect v-model="assignee" class="w-full" aria-label="Responsable">
                                <option :value="null">Non assigné</option>
                                <option v-for="user in users" :key="user.value" :value="user.value">{{ user.label }}</option>
                            </NativeSelect>
                            <Button type="submit" variant="secondary" :disabled="processing || assignee === l.assigned_to">Assigner</Button>
                        </form>
                        <p v-else class="text-sm">{{ l.assignee?.name ?? 'Non assigné' }}</p>
                    </CardContent>
                </Card>
            </div>

            <div class="grid content-start gap-4 lg:col-span-2">
                <Card v-if="opportunity">
                    <CardHeader><CardTitle>Opportunité créée</CardTitle></CardHeader>
                    <CardContent class="flex items-center justify-between gap-3 text-sm">
                        <div class="min-w-0">
                            <Link :href="route('crm.opportunities.show', opportunity.id)" class="block truncate font-medium hover:underline">{{ opportunity.title }}</Link>
                            <p class="text-xs text-muted-foreground">{{ opportunity.reference }} · converti le {{ formatDate(l.converted_at) }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            <span class="tabular-nums text-muted-foreground">{{ formatCurrency(opportunity.value) }}</span>
                            <Badge :variant="variant('stage', opportunity.stage)">{{ label('stage', opportunity.stage) }}</Badge>
                        </div>
                    </CardContent>
                </Card>

                <ActivityList :activities="activities.data" related-type="lead" :related-id="l.id" />
            </div>
        </div>
    </AppLayout>
</template>
