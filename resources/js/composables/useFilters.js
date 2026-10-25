import { reactive, watch } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Reactive list filters kept in the query string. Changes reload the page
 * props (debounced) while keeping local state such as the search input.
 */
export function useFilters(routeName, initial, defaults = {}) {
    const filters = reactive({ ...defaults, ...initial });
    let timer = null;

    watch(filters, () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            const query = Object.fromEntries(
                Object.entries(filters).filter(([, value]) => value !== '' && value !== null && value !== false && value !== undefined),
            );
            router.get(route(routeName), query, { preserveState: true, preserveScroll: true, replace: true });
        }, 300);
    });

    return filters;
}
