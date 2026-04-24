import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    server: {
        host: '127.0.0.1',
        port: 5173,
    },
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules/echarts')) {
                        return 'echarts-core';
                    }

                    if (id.includes('node_modules/zrender')) {
                        return 'zrender';
                    }

                    if (id.includes('node_modules/vue-echarts')) {
                        return 'vue-echarts';
                    }

                    if (id.includes('node_modules/@inertiajs/vue3')) {
                        return 'inertia';
                    }

                    if (id.includes('node_modules/vue')) {
                        return 'vendor';
                    }
                },
            },
        },
    },
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
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
        wayfinder({
            formVariants: true,
        }),
    ],
});
