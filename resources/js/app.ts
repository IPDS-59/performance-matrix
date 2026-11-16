import '../css/app.css';
import './bootstrap';

import { createInertiaApp, http, router } from '@inertiajs/vue3';
import { rememberCsrfToken, withCsrfHeader } from '@/composables/useCsrf';
import type { App, DefineComponent } from 'vue';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createPinia } from 'pinia';
import { ZiggyVue } from 'ziggy-js';
import { createNotivue } from 'notivue';
import 'notivue/notification.css';
import 'notivue/animations.css';
import { formatNumber } from '@/composables/useNumber';

const push = createNotivue({ notifications: { global: { duration: 4000 } } });

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Inertia reads the XSRF-TOKEN cookie, which the BPS load balancer makes HttpOnly: send the page's token instead.
http.onRequest((config: { url: string; headers?: Record<string, string> }) => withCsrfHeader(config));
router.on('navigate', (event: { detail: { page: { props: Record<string, unknown> } } }) => rememberCsrfToken(event.detail.page.props.csrf_token));

createInertiaApp({
    title: (title: string) => `${title} - ${appName}`,
    resolve: (name: string) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./Pages/**/*.vue'),
        ),
    withApp(app: App) {
        app.config.globalProperties.$n = formatNumber;
        app.use(createPinia())
            .use(ZiggyVue)
            .use(push);
    },
    progress: {
        color: '#1B4B8A',
    },
});
