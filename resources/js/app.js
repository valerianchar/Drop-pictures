import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import AppLayout from './layouts/AppLayout.vue';
import { registerServiceWorker } from './pwa';

const appName = import.meta.env.VITE_APP_NAME || 'Drop Picture';

createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),
    resolve: (name) => {
        const pages = import.meta.glob('./pages/**/*.vue', { eager: true });
        const page = pages[`./pages/${name}.vue`];

        // Les écrans authentifiés portent l'en-tête par défaut ; les écrans
        // invités et publics déclarent explicitement `layout = null`.
        page.default.layout = page.default.layout === undefined ? AppLayout : page.default.layout;

        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#3dff7a',
        showSpinner: false,
    },
});

registerServiceWorker();
