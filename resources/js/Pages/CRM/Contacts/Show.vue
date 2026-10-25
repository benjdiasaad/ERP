<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Mail, Pencil, Phone, Trash2 } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import ActivityList from '@/components/crm/ActivityList.vue';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription,
    AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { usePermissions } from '@/composables/usePermissions';
import { formatCurrency, label, variant } from '@/composables/format';

const props = defineProps({
    contact: { type: Object, required: true },
    leads: { type: Array, required: true },
    opportunities: { type: Array, required: true },
    activities: { type: Object, required: true },
});

const { can } = usePermissions();
const c = computed(() => props.contact.data);

const destroy = () => router.delete(route('crm.contacts.destroy', c.value.id));

</script>

<template>
    <AppLayout :title="c.full_name"
        :breadcrumbs="[{ title: 'CRM' }, { title: 'Contacts', href: route('crm.contacts.index') }, { title: c.full_name }]">
        <PageHeader :title="c.full_name" :description="[c.job_title, c.organization].filter(Boolean).join(' · ') || null">
            <template #actions>
                <Button v-if="can('contacts.update')" variant="outline" as-child>
                    <Link :href="route('crm.contacts.edit', c.id)"><Pencil /> Modifier</Link>
                </Button>
                <AlertDialog v-if="can('contacts.delete')">
                    <AlertDialogTrigger as-child>
                        <Button variant="destructive"><Trash2 /> Supprimer</Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Supprimer {{ c.full_name }} ?</AlertDialogTitle>
                            <AlertDialogDescription>
                                Le contact sera retiré des listes. Ses leads, opportunités et activités sont conservés.
                            </AlertDialogDescription>
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
                <CardHeader><CardTitle>Informations</CardTitle></CardHeader>
                <CardContent>
                    <dl class="grid gap-3 text-sm">
                        <div class="flex min-w-0 items-center gap-2">
                            <Mail class="size-4 shrink-0 text-muted-foreground" />
                            <a v-if="c.email" :href="`mailto:${c.email}`" class="truncate hover:underline">{{ c.email }}</a>
                            <span v-else class="text-muted-foreground">—</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <Phone class="size-4 shrink-0 text-muted-foreground" />
                            <span>{{ c.phone ?? c.mobile ?? '—' }}</span>
                        </div>
                        <Separator />
                        <div><dt class="text-muted-foreground">Client lié</dt><dd class="font-medium">{{ c.customer?.name ?? '—' }}</dd></div>
                        <div><dt class="text-muted-foreground">Fournisseur lié</dt><dd class="font-medium">{{ c.supplier?.name ?? '—' }}</dd></div>
                        <div><dt class="text-muted-foreground">Source</dt><dd>{{ label('source', c.source) }}</dd></div>
                        <div><dt class="text-muted-foreground">Ville</dt><dd>{{ [c.city, c.country].filter(Boolean).join(', ') || '—' }}</dd></div>
                        <div><dt class="text-muted-foreground">Statut</dt>
                            <dd><Badge :variant="c.is_active ? 'secondary' : 'outline'">{{ c.is_active ? 'Actif' : 'Inactif' }}</Badge></dd>
                        </div>
                        <div v-if="c.notes"><dt class="text-muted-foreground">Notes</dt><dd class="whitespace-pre-line">{{ c.notes }}</dd></div>
                    </dl>
                </CardContent>
            </Card>

            <div class="grid content-start gap-4 lg:col-span-2">
                <Card>
                    <CardHeader><CardTitle>Opportunités</CardTitle></CardHeader>
                    <CardContent>
                        <ul v-if="opportunities.length" class="grid gap-3">
                            <li v-for="o in opportunities" :key="o.id" class="flex items-center justify-between gap-3 text-sm">
                                <div class="min-w-0"><Link :href="route('crm.opportunities.show', o.id)" class="block truncate font-medium hover:underline">{{ o.title }}</Link><p class="text-xs text-muted-foreground">{{ o.reference }}</p></div>
                                <div class="flex shrink-0 items-center gap-3">
                                    <span class="tabular-nums text-muted-foreground">{{ formatCurrency(o.value) }}</span>
                                    <Badge :variant="variant('stage', o.stage)">{{ label('stage', o.stage) }}</Badge>
                                </div>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-muted-foreground">Aucune opportunité.</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Leads</CardTitle></CardHeader>
                    <CardContent>
                        <ul v-if="leads.length" class="grid gap-3">
                            <li v-for="l in leads" :key="l.id" class="flex items-center justify-between gap-3 text-sm">
                                <div class="min-w-0"><Link :href="route('crm.leads.show', l.id)" class="block truncate font-medium hover:underline">{{ l.title }}</Link><p class="text-xs text-muted-foreground">{{ l.reference }}</p></div>
                                <Badge :variant="variant('leadStatus', l.status)">{{ label('leadStatus', l.status) }}</Badge>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-muted-foreground">Aucun lead.</p>
                    </CardContent>
                </Card>

                <ActivityList :activities="activities.data" related-type="contact" :related-id="c.id" />
            </div>
        </div>
    </AppLayout>
</template>
