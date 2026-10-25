import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';

/**
 * POST to a workflow endpoint (qualify, win, complete…) outside of a form.
 * Server-side refusals (ValidationException) are shown as a toast; field
 * errors stay available in `errors` for dialogs that display them inline.
 */
export function useAction() {
    const processing = ref(false);
    const errors = ref({});

    const run = (url, data = {}, options = {}) => router.post(url, data, {
        preserveScroll: true,
        ...options,
        onStart: () => {
            processing.value = true;
            errors.value = {};
        },
        onFinish: () => {
            processing.value = false;
        },
        onError: (bag) => {
            errors.value = bag;
            const fieldErrors = options.fields ?? [];
            const general = Object.entries(bag).filter(([key]) => !fieldErrors.includes(key));
            if (general.length) toast.error(general[0][1]);
            options.onError?.(bag);
        },
    });

    return { run, processing, errors };
}
