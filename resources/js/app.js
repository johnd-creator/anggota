import { createApp, h } from 'vue'
import { createInertiaApp, router, usePage } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import '../css/app.css';
import { useTextFormat } from '@/Composables/useTextFormat';

createInertiaApp({
    resolve: name => {
        // Lazy-load pages to enable route-level code splitting (smaller initial JS bundle).
        return resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue'))
    },
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
        
        app.config.globalProperties.$toTitleCase = useTextFormat().toTitleCase;
        
        app.mount(el)

        const page = usePage();
        let lastUserId = page.props?.auth?.user?.id || null;
        router.on('finish', () => {
            const currentId = page.props?.auth?.user?.id;
            if (currentId && lastUserId && currentId !== lastUserId) {
                window.location.reload();
            }
            lastUserId = currentId;
        });

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
