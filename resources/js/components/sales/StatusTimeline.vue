<script setup>
import { formatDateTime } from '@/composables/format';

/** steps: [{ label, date?, done, failed? }] */
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
                <p v-if="step.date" class="text-xs text-muted-foreground">{{ formatDateTime(step.date) }}</p>
            </div>
        </li>
    </ol>
</template>
