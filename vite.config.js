import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { glob } from 'glob'; 

export default defineConfig({
    plugins: [
        laravel({
            input: [
                // ═══════════════════════════════════════
                // الحزمة العامة (كل الصفحات)
                // ═══════════════════════════════════════
                'resources/css/app.css',
                'resources/js/app.js',

                // ═══════════════════════════════════════
                // حزم الصفحات
                // ═══════════════════════════════════════
                ...glob.sync('resources/js/pages/*.js'),
            ],
            refresh: true,
        }),
    ],
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    vendor: ['bootstrap', 'axios'],
                },
            },
        },
    },
});