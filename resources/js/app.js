import './bootstrap';
import '../css/app.css';
import 'vue-sonner/style.css';

import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { toast } from 'vue-sonner';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { Toaster } from '@/components/ui/sonner';

const appName = import.meta.env.VITE_APP_NAME || 'ERP';

// Server flash messages (session 'success' / 'error') become toasts.
const showFlash = (flash) => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
};

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        // The Toaster lives at the root so it survives page navigations.
        const app = createApp({
            render: () => [h(App, props), h(Toaster, { richColors: true, position: 'bottom-right' })],
        })
            .use(plugin)
            .use(ZiggyVue);

        app.mount(el);

        router.on('success', (event) => showFlash(event.detail.page.props.flash));
        showFlash(props.initialPage.props.flash);

        return app;
    },
    progress: {
        color: '#171717',
    },
});
