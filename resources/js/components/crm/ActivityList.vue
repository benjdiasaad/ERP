<script setup>
import { Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import CompleteActivityDialog from '@/components/crm/CompleteActivityDialog.vue';
import { usePermissions } from '@/composables/usePermissions';
import { formatDateTime, label, variant } from '@/composables/format';

/** Activities linked to one record, with "add" and "complete" actions. */
defineProps({
    activities: { type: Array, required: true },
    relatedType: { type: String, required: true },
    relatedId: { type: Number, required: true },
});

const { can } = usePermissions();
const isOpen = (activity) => ['pending', 'in_progress'].includes(activity.status);
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Activités</CardTitle>
            <CardAction v-if="can('activities.create')">
                <Button variant="outline" size="sm" as-child>
                    <Link :href="route('crm.activities.create', { related_type: relatedType, related_id: relatedId })"><Plus /> Ajouter</Link>
                </Button>
            </CardAction>
        </CardHeader>
        <CardContent>
            <div v-if="activities.length" class="grid gap-3">
                <template v-for="(activity, index) in activities" :key="activity.id">
                    <Separator v-if="index > 0" />
                    <div class="flex items-start justify-between gap-3 text-sm">
                        <div class="min-w-0">
                            <p class="font-medium" :class="activity.is_overdue ? 'text-destructive' : ''">{{ activity.subject }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ label('activityType', activity.type) }} · {{ formatDateTime(activity.due_date) }}
                                <template v-if="activity.assignee"> · {{ activity.assignee.name }}</template>
                            </p>
                            <p v-if="activity.outcome" class="mt-1 text-muted-foreground">{{ activity.outcome }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <CompleteActivityDialog v-if="isOpen(activity) && can('activities.complete')" :activity="activity" size="xs" />
                            <Badge :variant="variant('activityStatus', activity.status)">{{ label('activityStatus', activity.status) }}</Badge>
                        </div>
                    </div>
                </template>
            </div>
            <p v-else class="text-sm text-muted-foreground">Aucune activité.</p>
        </CardContent>
    </Card>
</template>
