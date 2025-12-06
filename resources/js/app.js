import { createApp, h } from 'vue'
import { createInertiaApp, router } from '@inertiajs/vue3'
import '../css/app.css';

createInertiaApp({
    resolve: name => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true })
        return pages[`./Pages/${name}.vue`]
    },
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
        app.mount(el)

        router.on('invalid', () => {
            window.location.reload();
        });

        if (import.meta.env.PROD && 'serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/service-worker.js').catch(() => {});
            });
        }
    },
})
