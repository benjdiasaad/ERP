<script setup>
import { formatDate, formatDateTime } from '@/composables/format';

/** steps: [{ label, date?, dateOnly?, done, failed? }] — dateOnly for date columns (no time). */
defineProps({
    steps: { type: Array, required: true },
});
</script>

<template>
    <ol class="grid gap-3">
        <li v-for="step in steps" :key="step.label" class="flex items-start gap-3">
            <span class="mt-1 size-2.5 shrink-0 rounded-full"
                :class="step.failed ? 'bg-destructive' : step.done ? 'bg-primary' : 'border bg-background'" />
            <div>
                <p :class="step.done ? 'font-medium' : 'text-muted-foreground'">{{ step.label }}</p>
                <p v-if="step.date" class="text-xs text-muted-foreground">{{ step.dateOnly ? formatDate(step.date) : formatDateTime(step.date) }}</p>
            </div>
        </li>
    </ol>
</template>
