<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { ListTree, Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { toast } from 'vue-sonner';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import FormField from '@/components/FormField.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useFilters } from '@/composables/useFilters';
import { usePermissions } from '@/composables/usePermissions';
import { formatAmount, label, labels } from '@/composables/format';

const props = defineProps({
    accounts: { type: Object, required: true },
    parents: { type: Array, required: true },
    filters: { type: Object, required: true },
});

const { can } = usePermissions();
const filters = useFilters('finance.accounts.index', props.filters, { search: '', type: '' });
const typeOptions = Object.entries(labels.accountType).map(([value, text]) => ({ value, label: text }));

const open = ref(false);
const editing = ref(null);
const form = useForm({ code: '', name: '', type: 'asset', parent_id: null, description: '', is_active: true });
// An account cannot be its own parent.
const parentOptions = computed(() => props.parents.filter((p) => p.value !== editing.value?.id));

const openForm = (account = null) => {
    editing.value = account;
    form.clearErrors();
    form.defaults({
        code: account?.code ?? '', name: account?.name ?? '', type: account?.type ?? 'asset',
        parent_id: account?.parent_id ?? null, description: account?.description ?? '', is_active: account?.is_active ?? true,
    }).reset();
    open.value = true;
};

const submit = () => {
    const options = { preserveScroll: true, onSuccess: () => { open.value = false; } };
    if (editing.value) form.put(route('finance.accounts.update', editing.value.id), options);
    else form.post(route('finance.accounts.store'), options);
};

const destroy = (account) => router.delete(route('finance.accounts.destroy', account.id), {
    preserveScroll: true,
    onError: (errors) => toast.error(Object.values(errors)[0]),
});
</script>

<template>
    <AppLayout title="Plan comptable" :breadcrumbs="[{ title: 'Finance' }, { title: 'Plan comptable' }]">
        <PageHeader title="Plan comptable" description="Comptes utilisés pour passer les écritures.">
            <template #actions>
                <Button v-if="can('chart_of_accounts.create')" @click="openForm()"><Plus /> Nouveau compte</Button>
            </template>
        </PageHeader>

        <Card class="gap-4 py-4">
            <CardContent class="flex flex-col gap-3 px-4 sm:flex-row">
                <div class="relative flex-1">
                    <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="filters.search" type="search" placeholder="Numéro ou intitulé…" class="pl-8" aria-label="Rechercher" />
                </div>
                <NativeSelect v-model="filters.type" aria-label="Type" class="sm:w-48">
                    <option value="">Tous types</option>
                    <option v-for="t in typeOptions" :key="t.value" :value="t.value">{{ t.label }}</option>
                </NativeSelect>
            </CardContent>

            <Table v-if="accounts.data.length">
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Compte</TableHead>
                        <TableHead class="hidden sm:table-cell">Type</TableHead>
                        <TableHead class="hidden md:table-cell">Compte parent</TableHead>
                        <TableHead class="hidden text-right lg:table-cell">Solde</TableHead>
                        <TableHead class="pr-4"><span class="sr-only">Actions</span></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="account in accounts.data" :key="account.id" :class="account.is_active ? '' : 'opacity-60'">
                        <TableCell class="pl-4">
                            <span class="font-mono font-medium">{{ account.code }}</span>
                            <span class="ml-2">{{ account.name }}</span>
                            <Badge v-if="!account.is_active" variant="outline" class="ml-2">Inactif</Badge>
                        </TableCell>
                        <TableCell class="hidden text-muted-foreground sm:table-cell">{{ label('accountType', account.type) }}</TableCell>
                        <TableCell class="hidden text-muted-foreground md:table-cell">
                            <template v-if="account.parent">{{ account.parent.code }} — {{ account.parent.name }}</template><template v-else>—</template>
                        </TableCell>
                        <TableCell class="hidden text-right tabular-nums lg:table-cell">{{ formatAmount(account.balance) }}</TableCell>
                        <TableCell class="pr-4 text-right whitespace-nowrap">
                            <Button v-if="can('chart_of_accounts.update')" variant="ghost" size="icon" :aria-label="`Modifier ${account.code}`" @click="openForm(account)"><Pencil /></Button>
                            <Button v-if="can('chart_of_accounts.delete')" variant="ghost" size="icon" :aria-label="`Supprimer ${account.code}`" @click="destroy(account)"><Trash2 /></Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <Empty v-else>
                <EmptyHeader>
                    <EmptyMedia variant="icon"><ListTree /></EmptyMedia>
                    <EmptyTitle>Aucun compte</EmptyTitle>
                    <EmptyDescription>Créez les comptes du plan comptable (ex. : 5141 Banque, 4411 Fournisseurs).</EmptyDescription>
                </EmptyHeader>
            </Empty>
        </Card>

        <Dialog v-model:open="open">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ editing ? `Modifier le compte ${editing.code}` : 'Nouveau compte' }}</DialogTitle>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <FormField id="account-code" v-model="form.code" label="Numéro" placeholder="5141" required :error="form.errors.code" />
                        <FormField id="account-name" v-model="form.name" label="Intitulé" required class="sm:col-span-2" :error="form.errors.name" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField id="account-type" v-model="form.type" label="Type" :options="typeOptions" required :error="form.errors.type" />
                        <FormField id="account-parent" v-model="form.parent_id" label="Compte parent" :options="parentOptions" placeholder="Aucun" :error="form.errors.parent_id" />
                    </div>
                    <FormField id="account-description" v-model="form.description" label="Description" type="textarea" :error="form.errors.description" />
                    <div class="flex items-center gap-2">
                        <Checkbox id="account-active" v-model="form.is_active" />
                        <Label for="account-active" class="font-normal">Compte actif</Label>
                    </div>
                    <DialogFooter>
                        <Button type="submit" :disabled="form.processing">{{ editing ? 'Enregistrer' : 'Créer' }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
