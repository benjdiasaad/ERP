<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { FilePlus, Mail, MapPin, Pencil, Phone, Trash2 } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import FormField from '@/components/FormField.vue';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription,
    AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import { usePermissions } from '@/composables/usePermissions';
import { formatAmount, formatDate, label, labels, variant } from '@/composables/format';

const props = defineProps({
    personnel: { type: Object, required: true },
    contracts: { type: Object, default: null },
    leaves: { type: Object, default: null },
});

const { can } = usePermissions();
const p = computed(() => props.personnel.data);
const destroy = () => router.delete(route('organisation.personnels.destroy', p.value.id));
const typeOptions = Object.entries(labels.contractType).map(([value, text]) => ({ value, label: text }));

const contractOpen = ref(false);
const contractForm = useForm({ personnel_id: null, type: 'CDI', start_date: null, end_date: null, trial_period_end_date: null, salary: null, working_hours_per_week: 44, status: 'active', notes: '' });
const openContract = () => {
    contractForm.reset();
    contractForm.clearErrors();
    contractForm.personnel_id = p.value.id;
    contractForm.start_date = new Date().toISOString().slice(0, 10);
};
const submitContract = () => contractForm.post(route('organisation.contracts.store'), { preserveScroll: true, onSuccess: () => { contractOpen.value = false; } });

const terminating = ref(null);
const terminateForm = useForm({ termination_date: null, reason: '' });
const openTerminate = (contract) => {
    terminateForm.reset();
    terminateForm.clearErrors();
    terminateForm.termination_date = new Date().toISOString().slice(0, 10);
    terminating.value = contract;
};
const submitTerminate = () => terminateForm.post(route('organisation.contracts.terminate', terminating.value.id), {
    preserveScroll: true, onSuccess: () => { terminating.value = null; },
});
</script>

<template>
    <AppLayout :title="`${p.first_name} ${p.last_name}`" :breadcrumbs="[{ title: 'Organisation' }, { title: 'Personnel', href: route('organisation.personnels.index') }, { title: `${p.last_name} ${p.first_name}` }]">
        <PageHeader :title="`${p.first_name} ${p.last_name}`" :description="[p.matricule, p.position?.name, p.department?.name].filter(Boolean).join(' · ')">
            <template #actions>
                <Badge :variant="variant('personnelStatus', p.status)">{{ label('personnelStatus', p.status) }}</Badge>
                <Button v-if="can('personnels.update')" variant="outline" as-child>
                    <Link :href="route('organisation.personnels.edit', p.id)"><Pencil /> Modifier</Link>
                </Button>
                <AlertDialog v-if="can('personnels.delete')">
                    <AlertDialogTrigger as-child>
                        <Button variant="ghost" size="icon" aria-label="Supprimer"><Trash2 /></Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Supprimer la fiche de {{ p.first_name }} {{ p.last_name }} ?</AlertDialogTitle>
                            <AlertDialogDescription>Pour un départ, préférez le statut « Sorti » : l'historique reste consultable.</AlertDialogDescription>
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
                <CardHeader><CardTitle>Fiche</CardTitle></CardHeader>
                <CardContent>
                    <dl class="grid gap-3 text-sm">
                        <div class="flex min-w-0 items-center gap-2"><Mail class="size-4 shrink-0 text-muted-foreground" />
                            <a v-if="p.email" :href="`mailto:${p.email}`" class="truncate hover:underline">{{ p.email }}</a><span v-else class="text-muted-foreground">—</span>
                        </div>
                        <div class="flex items-center gap-2"><Phone class="size-4 shrink-0 text-muted-foreground" />{{ p.mobile ?? p.phone ?? '—' }}</div>
                        <div class="flex items-start gap-2"><MapPin class="mt-0.5 size-4 shrink-0 text-muted-foreground" />{{ [p.address, p.city].filter(Boolean).join(', ') || '—' }}</div>
                        <Separator />
                        <div class="grid grid-cols-2 gap-2">
                            <div><dt class="text-muted-foreground">Embauche</dt><dd>{{ formatDate(p.hire_date) }}</dd></div>
                            <div><dt class="text-muted-foreground">Type</dt><dd>{{ label('employmentType', p.employment_type) }}</dd></div>
                            <div><dt class="text-muted-foreground">CIN</dt><dd>{{ p.national_id ?? '—' }}</dd></div>
                            <div><dt class="text-muted-foreground">CNSS</dt><dd>{{ p.social_security_number ?? '—' }}</dd></div>
                            <div><dt class="text-muted-foreground">Naissance</dt><dd>{{ formatDate(p.birth_date) }}</dd></div>
                            <div v-if="p.termination_date"><dt class="text-muted-foreground">Sortie</dt><dd>{{ formatDate(p.termination_date) }}</dd></div>
                        </div>
                        <template v-if="p.emergency_contact_name">
                            <Separator />
                            <div><dt class="text-muted-foreground">Contact d'urgence</dt>
                                <dd>{{ p.emergency_contact_name }}<span v-if="p.emergency_contact_relation" class="text-muted-foreground"> ({{ p.emergency_contact_relation }})</span></dd>
                                <dd v-if="p.emergency_contact_phone" class="text-muted-foreground">{{ p.emergency_contact_phone }}</dd>
                            </div>
                        </template>
                        <div v-if="p.notes"><dt class="text-muted-foreground">Notes</dt><dd class="whitespace-pre-line">{{ p.notes }}</dd></div>
                    </dl>
                </CardContent>
            </Card>

            <div class="grid content-start gap-4 lg:col-span-2">
                <Card v-if="contracts">
                    <CardHeader>
                        <CardTitle>Contrats</CardTitle>
                        <CardAction v-if="can('contracts.create')">
                            <Dialog v-model:open="contractOpen" @update:open="(value) => value && openContract()">
                                <DialogTrigger as-child><Button variant="outline" size="sm"><FilePlus /> Nouveau contrat</Button></DialogTrigger>
                                <DialogContent>
                                    <DialogHeader><DialogTitle>Nouveau contrat</DialogTitle></DialogHeader>
                                    <form class="grid gap-4" @submit.prevent="submitContract">
                                        <div class="grid gap-4 sm:grid-cols-2">
                                            <FormField id="contract-type" v-model="contractForm.type" label="Type" :options="typeOptions" required :error="contractForm.errors.type" />
                                            <FormField id="contract-salary" v-model="contractForm.salary" label="Salaire brut mensuel" type="number" required :error="contractForm.errors.salary" />
                                            <FormField id="contract-start" v-model="contractForm.start_date" label="Début" type="date" required :error="contractForm.errors.start_date" />
                                            <FormField id="contract-end" v-model="contractForm.end_date" label="Fin (CDD, stage)" type="date" :error="contractForm.errors.end_date" />
                                            <FormField id="contract-trial" v-model="contractForm.trial_period_end_date" label="Fin de période d'essai" type="date" :error="contractForm.errors.trial_period_end_date" />
                                            <FormField id="contract-hours" v-model="contractForm.working_hours_per_week" label="Heures / semaine" type="number" :error="contractForm.errors.working_hours_per_week" />
                                        </div>
                                        <FormField id="contract-notes" v-model="contractForm.notes" label="Notes" type="textarea" :error="contractForm.errors.notes" />
                                        <DialogFooter><Button type="submit" :disabled="contractForm.processing">Créer le contrat</Button></DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        </CardAction>
                    </CardHeader>
                    <CardContent>
                        <ul v-if="contracts.data.length" class="grid gap-3">
                            <li v-for="c in contracts.data" :key="c.id" class="flex flex-wrap items-center justify-between gap-3 text-sm">
                                <div>
                                    <p class="font-medium">{{ label('contractType', c.type) }} · {{ c.reference }}</p>
                                    <p class="text-xs text-muted-foreground">Du {{ formatDate(c.start_date) }}<template v-if="c.end_date"> au {{ formatDate(c.end_date) }}</template></p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="tabular-nums">{{ formatAmount(c.salary, c.salary_currency ?? 'MAD') }}</span>
                                    <Badge :variant="variant('contractStatus', c.status)">{{ label('contractStatus', c.status) }}</Badge>
                                    <Button v-if="c.status === 'active' && can('contracts.update')" variant="ghost" size="sm" @click="openTerminate(c)">Résilier</Button>
                                </div>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-muted-foreground">Aucun contrat.</p>
                    </CardContent>
                </Card>

                <Card v-if="leaves">
                    <CardHeader>
                        <CardTitle>Congés récents</CardTitle>
                        <CardAction><Button variant="ghost" size="sm" as-child><Link :href="route('organisation.leaves.index', { search: p.matricule })">Tous les congés</Link></Button></CardAction>
                    </CardHeader>
                    <CardContent>
                        <ul v-if="leaves.data.length" class="grid gap-3">
                            <li v-for="l in leaves.data" :key="l.id" class="flex items-center justify-between gap-3 text-sm">
                                <div>
                                    <p class="font-medium">{{ label('leaveType', l.leave_type) }}</p>
                                    <p class="text-xs text-muted-foreground">{{ formatDate(l.start_date) }} → {{ formatDate(l.end_date) }} · {{ Number(l.total_days) }} j</p>
                                </div>
                                <Badge :variant="variant('leaveStatus', l.status)">{{ label('leaveStatus', l.status) }}</Badge>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-muted-foreground">Aucun congé.</p>
                    </CardContent>
                </Card>
            </div>
        </div>

        <Dialog :open="!!terminating" @update:open="(value) => { if (!value) terminating = null; }">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Résilier le contrat {{ terminating?.reference }}</DialogTitle>
                    <DialogDescription>Le contrat prend fin à la date indiquée.</DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="submitTerminate">
                    <FormField id="terminate-date" v-model="terminateForm.termination_date" label="Date de fin" type="date" required :error="terminateForm.errors.termination_date" />
                    <FormField id="terminate-reason" v-model="terminateForm.reason" label="Motif" type="textarea" required :error="terminateForm.errors.reason" />
                    <DialogFooter><Button type="submit" variant="destructive" :disabled="terminateForm.processing">Résilier</Button></DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
