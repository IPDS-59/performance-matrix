import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import type { App, DefineComponent } from 'vue';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createPinia } from 'pinia';
import { ZiggyVue } from 'ziggy-js';
import { createNotivue } from 'notivue';
import 'notivue/notification.css';
import 'notivue/animations.css';

const push = createNotivue({ notifications: { global: { duration: 4000 } } });

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title: string) => `${title} - ${appName}`,
    resolve: (name: string) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./Pages/**/*.vue'),
        ),
    withApp(app: App) {
        app.use(createPinia())
            .use(ZiggyVue)
            .use(push);
    },
    progress: {
        color: '#1B4B8A',
    },
});
