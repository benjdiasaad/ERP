<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: null },
});
</script>

<template>
    <!-- The actions wrap under the title when there is not enough room beside it. -->
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0 flex-1 basis-72 space-y-1">
            <h1 class="text-2xl font-semibold tracking-tight text-balance break-words">{{ title }}</h1>
            <p v-if="description" class="text-sm text-muted-foreground">{{ description }}</p>
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
