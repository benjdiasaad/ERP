<script setup>
import { computed } from 'vue';
import { CalendarDays, CircleAlert } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty';
import { Progress } from '@/components/ui/progress';
import { Separator } from '@/components/ui/separator';
import { formatCurrency, formatDateTime, formatNumber, label } from '@/composables/format';

const props = defineProps({
    stats: { type: Array, required: true },
    pipeline: { type: Array, default: null },
    activities: { type: Object, default: null },
    events: { type: Array, default: null },
});

const maxStageValue = computed(() => Math.max(1, ...(props.pipeline ?? []).map((s) => Number(s.value))));
const formatStat = (stat) => (stat.format === 'currency' ? formatCurrency(stat.value) : formatNumber(stat.value));
const priorityVariant = { high: 'destructive', medium: 'secondary', low: 'outline' };
const nothingVisible = computed(() => !props.stats.length && !props.pipeline && !props.activities && !props.events);
</script>

<template>
    <AppLayout title="Tableau de bord" :breadcrumbs="[{ title: 'Tableau de bord' }]">
        <PageHeader title="Tableau de bord" description="Vue d'ensemble de l'activité de la société." />

        <div v-if="stats.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card v-for="stat in stats" :key="stat.label" class="gap-2">
                <CardHeader>
                    <CardDescription>{{ stat.label }}</CardDescription>
                    <CardTitle class="text-2xl tabular-nums">{{ formatStat(stat) }}</CardTitle>
                </CardHeader>
                <CardContent v-if="stat.hint" class="text-xs text-muted-foreground">{{ stat.hint }}</CardContent>
            </Card>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <Card v-if="pipeline" class="min-w-0 lg:col-span-2">
                <CardHeader>
                    <CardTitle>Pipeline commercial</CardTitle>
                    <CardDescription>Opportunités ouvertes par étape, valeur totale et pondérée.</CardDescription>
                </CardHeader>
                <CardContent class="grid gap-5">
                    <div v-for="stage in pipeline" :key="stage.stage" class="grid gap-2">
                        <div class="flex flex-wrap items-baseline justify-between gap-x-3 text-sm">
                            <span class="font-medium">{{ label('stage', stage.stage) }}
                                <span class="font-normal text-muted-foreground">· {{ stage.count }}</span>
                            </span>
                            <span class="tabular-nums">{{ formatCurrency(stage.value) }}
                                <span class="text-muted-foreground">(pondéré {{ formatCurrency(stage.weighted_value) }})</span>
                            </span>
                        </div>
                        <Progress :model-value="(Number(stage.value) / maxStageValue) * 100" />
                    </div>
                </CardContent>
            </Card>

            <Card v-if="events" class="min-w-0">
                <CardHeader>
                    <CardTitle>Prochains événements</CardTitle>
                </CardHeader>
                <CardContent>
                    <ul v-if="events.length" class="grid gap-4">
                        <li v-for="event in events" :key="event.id" class="flex min-w-0 gap-3">
                            <CalendarDays class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">{{ event.title }}</p>
                                <p class="text-xs text-muted-foreground">{{ formatDateTime(event.start_date) }} · {{ label('eventType', event.type) }}</p>
                            </div>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">Aucun événement à venir.</p>
                </CardContent>
            </Card>

            <Card v-if="activities" class="lg:col-span-3">
                <CardHeader>
                    <CardTitle>Activités</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-6 md:grid-cols-2">
                    <div class="grid content-start gap-3">
                        <p class="flex items-center gap-1.5 text-sm font-medium text-destructive">
                            <CircleAlert class="size-4" /> En retard
                        </p>
                        <template v-if="activities.overdue.length">
                            <div v-for="(activity, i) in activities.overdue" :key="activity.id" class="grid gap-3">
                                <Separator v-if="i > 0" />
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm">{{ activity.subject }}</p>
                                        <p class="text-xs text-muted-foreground">{{ label('activityType', activity.type) }} · {{ formatDateTime(activity.due_date) }}</p>
                                    </div>
                                    <Badge :variant="priorityVariant[activity.priority]">{{ label('priority', activity.priority) }}</Badge>
                                </div>
                            </div>
                        </template>
                        <p v-else class="text-sm text-muted-foreground">Rien en retard.</p>
                    </div>
                    <div class="grid content-start gap-3">
                        <p class="text-sm font-medium">Cette semaine</p>
                        <template v-if="activities.upcoming.length">
                            <div v-for="(activity, i) in activities.upcoming" :key="activity.id" class="grid gap-3">
                                <Separator v-if="i > 0" />
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm">{{ activity.subject }}</p>
                                        <p class="text-xs text-muted-foreground">{{ label('activityType', activity.type) }} · {{ formatDateTime(activity.due_date) }}</p>
                                    </div>
                                    <span v-if="activity.assignee" class="shrink-0 text-xs text-muted-foreground">{{ activity.assignee }}</span>
                                </div>
                            </div>
                        </template>
                        <p v-else class="text-sm text-muted-foreground">Aucune activité prévue.</p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Empty v-if="nothingVisible" class="border">
            <EmptyHeader>
                <EmptyTitle>Bienvenue</EmptyTitle>
                <EmptyDescription>Aucun module n'est accessible avec vos permissions dans cette société.</EmptyDescription>
            </EmptyHeader>
        </Empty>
    </AppLayout>
</template>
