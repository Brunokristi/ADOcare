import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import Components from 'unplugin-vue-components/vite';
import { PrimeVueResolver } from '@primevue/auto-import-resolver';
import { visualizer } from "rollup-plugin-visualizer";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/js/app.ts',
                'resources/js/website/website.ts',
                'resources/css/app.css'
            ],
            refresh: true,
        }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        Components({
            resolvers: [
                PrimeVueResolver()
            ]
        }),
        visualizer({}),
    ],
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules')) {
                        if (id.includes('@primevue') || id.includes('@primeuix')) {
                            return 'vendor_@primevue'
                        }
                        if (id.includes('primevue') || id.includes('primeicons')) {
                            return 'vendor_primevue'
                        }
                        if (id.includes('leaflet')) return 'vendor_leaflet'
                        return 'vendor'
                    }
                }
            }
        }
    },
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});
