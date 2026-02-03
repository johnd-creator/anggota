import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
            '@images': path.resolve(__dirname, './resources/images'),
            '@components': path.resolve(__dirname, './resources/js/Components'),
        },
    },
    server: {
        host: '127.0.0.1',
        port: 5173,
        strictPort: true,
        hmr: {
            host: '127.0.0.1',
        },
    },
    build: {
        chunkSizeWarningLimit: 1000,
        minify: 'esbuild',
        rollupOptions: {
            output: {
                manualChunks: (id) => {
                    // Core Vue framework
                    if (id.includes('node_modules/vue') || id.includes('node_modules/@vue')) {
                        return 'vue-core';
                    }
                    
                    // Inertia.js
                    if (id.includes('node_modules/@inertiajs')) {
                        return 'inertia';
                    }
                    
                    // UI libraries
                    if (id.includes('node_modules/lucide') || 
                        id.includes('node_modules/@headlessui') ||
                        id.includes('node_modules/heroicons')) {
                        return 'ui-vendor';
                    }
                    
                    // Rich text editor
                    if (id.includes('node_modules/quill') || 
                        id.includes('node_modules/vue-quill')) {
                        return 'editor';
                    }
                    
                    // Utility libraries
                    if (id.includes('node_modules/lodash') ||
                        id.includes('node_modules/axios') ||
                        id.includes('node_modules/date-fns')) {
                        return 'utils';
                    }
                    
                    // Charts
                    if (id.includes('node_modules/chart.js') || 
                        id.includes('node_modules/vue-chartjs')) {
                        return 'charts';
                    }
                    
                    // Forms validation
                    if (id.includes('node_modules/vee-validate') ||
                        id.includes('node_modules/yup')) {
                        return 'validation';
                    }
                    
                    // Livewire (if used)
                    if (id.includes('node_modules/livewire')) {
                        return 'livewire';
                    }
                    
                    // Default vendor chunk for remaining node_modules
                    if (id.includes('node_modules')) {
                        return 'vendor';
                    }
                },
                chunkFileNames: 'assets/js/[name]-[hash].js',
                entryFileNames: 'assets/js/[name]-[hash].js',
                assetFileNames: 'assets/[ext]/[name]-[hash].[ext]',
            },
        },
        cssCodeSplit: true,
        sourcemap: false,
    },
});
