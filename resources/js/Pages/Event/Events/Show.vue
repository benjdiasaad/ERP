<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { Ban, CalendarClock, CircleCheck, Flag, MapPin, Pencil, Trash2, UserPlus, X } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import FormField from '@/components/FormField.vue';
import ReasonDialog from '@/components/ReasonDialog.vue';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription,
    AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { NativeSelect } from '@/components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useAction } from '@/composables/useAction';
import { usePermissions } from '@/composables/usePermissions';
import { formatAmount, formatDateTime, label, labels, toLocalDateTimeInput, toServerDateTime, variant } from '@/composables/format';

const props = defineProps({
    event: { type: Object, required: true },
    personnels: { type: Array, default: () => [] },
});

const { can } = usePermissions();
// Computed: workflow actions reload props on this same page instance.
const e = computed(() => props.event.data);
const open = computed(() => !['completed', 'cancelled'].includes(e.value.status));
const { run, processing } = useAction();
const destroy = () => router.delete(route('organisation.events.destroy', e.value.id));

const postponeOpen = ref(false);
const postponeForm = useForm({ start_date: null, end_date: null, reason: '' });
const openPostpone = () => {
    postponeForm.reset();
    postponeForm.clearErrors();
    postponeForm.start_date = toLocalDateTimeInput(e.value.start_date);
    postponeForm.end_date = toLocalDateTimeInput(e.value.end_date);
};
const submitPostpone = () => postponeForm
    .transform((data) => ({ ...data, start_date: toServerDateTime(data.start_date), end_date: toServerDateTime(data.end_date) }))
    .post(route('organisation.events.postpone', e.value.id), { preserveScroll: true, onSuccess: () => { postponeOpen.value = false; } });

const roleOptions = Object.entries(labels.participantRole).map(([value, text]) => ({ value, label: text }));
const invite = useForm({ personnel_id: null, role: 'attendee' });
const submitInvite = () => invite.post(route('organisation.events.participants.store', e.value.id), {
    preserveScroll: true, onSuccess: () => invite.reset('personnel_id'),
    onError: (errors) => errors.participant && invite.setError('personnel_id', errors.participant),
});
const participantName = (p) => p.name
    ?? (p.personnel ? `${p.personnel.last_name} ${p.personnel.first_name}` : null)
    ?? (p.user ? `${p.user.first_name} ${p.user.last_name}` : p.email);
const rsvp = (p, status) => run(route('organisation.events.participants.rsvp', [e.value.id, p.id]), { rsvp_status: status });
const remove = (p) => router.delete(route('organisation.events.participants.destroy', [e.value.id, p.id]), { preserveScroll: true });
</script>

<template>
    <AppLayout :title="e.title" :breadcrumbs="[{ title: 'Organisation' }, { title: 'Événements', href: route('organisation.events.index') }, { title: e.title }]">
        <PageHeader :title="e.title" :description="`${e.reference ?? ''} · ${label('eventType', e.type)}${e.category ? ' · ' + e.category.name : ''}`">
            <template #actions>
                <Badge :variant="variant('eventStatus', e.status)">{{ label('eventStatus', e.status) }}</Badge>
                <Button v-if="e.status === 'planned' && can('events.confirm')" :disabled="processing"
                    @click="run(route('organisation.events.confirm', e.id))"><CircleCheck /> Confirmer</Button>
                <Button v-if="open && can('events.complete')" variant="outline" :disabled="processing"
                    @click="run(route('organisation.events.complete', e.id))"><Flag /> Terminer</Button>
                <Dialog v-if="open && can('events.postpone')" v-model:open="postponeOpen" @update:open="(value) => value && openPostpone()">
                    <DialogTrigger as-child><Button variant="outline"><CalendarClock /> Reporter</Button></DialogTrigger>
                    <DialogContent>
                        <DialogHeader><DialogTitle>Reporter l'événement</DialogTitle></DialogHeader>
                        <form class="grid gap-4" @submit.prevent="submitPostpone">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <FormField id="postpone-start" v-model="postponeForm.start_date" label="Nouveau début" type="datetime-local" required :error="postponeForm.errors.start_date" />
                                <FormField id="postpone-end" v-model="postponeForm.end_date" label="Nouvelle fin" type="datetime-local" required :error="postponeForm.errors.end_date" />
                            </div>
                            <FormField id="postpone-reason" v-model="postponeForm.reason" label="Motif" type="textarea" :error="postponeForm.errors.reason" />
                            <DialogFooter><Button type="submit" :disabled="postponeForm.processing">Reporter</Button></DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
                <Button v-if="open && can('events.update')" variant="outline" as-child>
                    <Link :href="route('organisation.events.edit', e.id)"><Pencil /> Modifier</Link>
                </Button>
                <ReasonDialog v-if="open && can('events.cancel')" :url="route('organisation.events.cancel', e.id)" title="Annuler l'événement"
                    :description="e.title" confirm-label="Annuler l'événement" trigger-variant="ghost"><Ban /> Annuler</ReasonDialog>
                <AlertDialog v-if="can('events.delete')">
                    <AlertDialogTrigger as-child><Button variant="ghost" size="icon" aria-label="Supprimer"><Trash2 /></Button></AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Supprimer {{ e.title }} ?</AlertDialogTitle>
                            <AlertDialogDescription>L'événement et ses invitations seront retirés des listes.</AlertDialogDescription>
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
                <CardHeader><CardTitle>Détails</CardTitle></CardHeader>
                <CardContent>
                    <dl class="grid gap-3 text-sm">
                        <div class="grid grid-cols-2 gap-2">
                            <div><dt class="text-muted-foreground">Début</dt><dd>{{ formatDateTime(e.start_date) }}</dd></div>
                            <div><dt class="text-muted-foreground">Fin</dt><dd>{{ formatDateTime(e.end_date) }}</dd></div>
                        </div>
                        <div class="flex items-start gap-2"><MapPin class="mt-0.5 size-4 shrink-0 text-muted-foreground" />{{ e.location ?? 'Lieu non précisé' }}</div>
                        <div v-if="e.budget"><dt class="text-muted-foreground">Budget</dt><dd class="tabular-nums">{{ formatAmount(e.budget) }}</dd></div>
                        <div v-if="e.is_mandatory"><Badge variant="outline">Présence obligatoire</Badge></div>
                        <div v-if="e.description"><dt class="text-muted-foreground">Description</dt><dd class="whitespace-pre-line">{{ e.description }}</dd></div>
                        <div v-if="e.status === 'postponed' && e.postponement_reason"><dt class="text-muted-foreground">Motif du report</dt><dd class="whitespace-pre-line">{{ e.postponement_reason }}</dd></div>
                        <div v-if="e.status === 'cancelled' && e.cancellation_reason"><dt class="text-muted-foreground">Motif d'annulation</dt><dd class="whitespace-pre-line">{{ e.cancellation_reason }}</dd></div>
                    </dl>
                </CardContent>
            </Card>

            <Card class="gap-4 pb-2 lg:col-span-2">
                <CardHeader><CardTitle>Participants ({{ e.participants?.length ?? 0 }})</CardTitle></CardHeader>
                <CardContent v-if="open && can('event_participants.invite')">
                    <form class="flex flex-col gap-2 sm:flex-row sm:items-start" @submit.prevent="submitInvite">
                        <div class="grid flex-1 gap-1">
                            <NativeSelect v-model="invite.personnel_id" aria-label="Collaborateur à inviter" class="w-full" :aria-invalid="!!invite.errors.personnel_id || undefined">
                                <option :value="null">Inviter un collaborateur…</option>
                                <option v-for="p in personnels" :key="p.value" :value="p.value">{{ p.label }}</option>
                            </NativeSelect>
                            <p v-if="invite.errors.personnel_id" class="text-sm text-destructive">{{ invite.errors.personnel_id }}</p>
                        </div>
                        <NativeSelect v-model="invite.role" aria-label="Rôle" class="sm:w-40">
                            <option v-for="r in roleOptions" :key="r.value" :value="r.value">{{ r.label }}</option>
                        </NativeSelect>
                        <Button type="submit" variant="secondary" :disabled="!invite.personnel_id || invite.processing"><UserPlus /> Inviter</Button>
                    </form>
                </CardContent>
                <Table v-if="e.participants?.length">
                    <TableHeader>
                        <TableRow>
                            <TableHead class="pl-4">Participant</TableHead>
                            <TableHead class="hidden sm:table-cell">Rôle</TableHead>
                            <TableHead>Réponse</TableHead>
                            <TableHead class="pr-4"><span class="sr-only">Actions</span></TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="p in e.participants" :key="p.id">
                            <TableCell class="pl-4"><p class="font-medium">{{ participantName(p) }}</p><p v-if="p.personnel?.matricule" class="text-xs text-muted-foreground">{{ p.personnel.matricule }}</p></TableCell>
                            <TableCell class="hidden text-muted-foreground sm:table-cell">{{ label('participantRole', p.role) }}</TableCell>
                            <TableCell><Badge :variant="variant('rsvpStatus', p.rsvp_status)">{{ label('rsvpStatus', p.rsvp_status) }}</Badge></TableCell>
                            <TableCell class="pr-4 text-right whitespace-nowrap">
                                <template v-if="open && can('event_participants.update')">
                                    <Button v-if="p.rsvp_status !== 'confirmed'" variant="ghost" size="sm" :disabled="processing" @click="rsvp(p, 'confirmed')">Présent</Button>
                                    <Button v-if="p.rsvp_status !== 'declined'" variant="ghost" size="sm" :disabled="processing" @click="rsvp(p, 'declined')">Absent</Button>
                                </template>
                                <Button v-if="open && can('event_participants.delete')" variant="ghost" size="icon" :aria-label="`Retirer ${participantName(p)}`" @click="remove(p)"><X /></Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <CardContent v-else><p class="text-sm text-muted-foreground">Aucun participant invité.</p></CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
