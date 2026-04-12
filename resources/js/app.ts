import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createPinia } from 'pinia';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import { createNotivue } from 'notivue';
import 'notivue/notification.css';
import 'notivue/animations.css';

const push = createNotivue({ notifications: { global: { duration: 4000 } } });

type SetupArg = NonNullable<Parameters<typeof createInertiaApp>[0]['setup']> extends (arg: infer T) => unknown ? T : never;

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title: string) => `${title} - ${appName}`,
    resolve: (name: string) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }: SetupArg) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(createPinia())
            .use(ZiggyVue)
            .use(push)
            .mount(el);
    },
    progress: {
        color: '#1B4B8A',
    },
});
