import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Permission checks for the current company, from the shared `auth.permissions` prop.
 * UI hints only: the server enforces every permission again.
 */
export function usePermissions() {
    const page = usePage();
    const permissions = computed(() => new Set(page.props.auth?.permissions ?? []));

    const can = (slug) => permissions.value.has(slug);

    return { can };
}
