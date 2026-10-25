<script setup>
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';

/**
 * Labelled input / select / textarea bound with v-model, showing a validation error.
 * For a select, pass `options` as [{ value, label }].
 */
const model = defineModel();

defineProps({
    label: { type: String, required: true },
    id: { type: String, required: true },
    type: { type: String, default: 'text' },
    error: { type: String, default: null },
    options: { type: Array, default: null },
    placeholder: { type: String, default: null },
    required: { type: Boolean, default: false },
    autocomplete: { type: String, default: null },
});
</script>

<template>
    <div class="grid gap-2">
        <Label :for="id">{{ label }}<span v-if="required" class="text-destructive">*</span></Label>

        <NativeSelect v-if="options" :id="id" v-model="model" class="w-full" :aria-invalid="!!error || undefined">
            <option :value="null">{{ placeholder ?? '—' }}</option>
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </NativeSelect>

        <Textarea v-else-if="type === 'textarea'" :id="id" v-model="model" rows="4" :placeholder="placeholder" :aria-invalid="!!error || undefined" />

        <Input v-else :id="id" v-model="model" :type="type" :placeholder="placeholder" :required="required"
            :autocomplete="autocomplete" :aria-invalid="!!error || undefined" />

        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
    </div>
</template>
