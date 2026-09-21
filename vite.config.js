import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

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
                'resources/js/pages/banks.js',
                'resources/js/pages/boxes.js',
                'resources/js/pages/coins.js',
                'resources/js/pages/customers.js',
                'resources/js/pages/items.js',
                'resources/js/pages/movements.js',
                'resources/js/pages/payment-voucher.js',
                'resources/js/pages/purchase-invoice.js',
                'resources/js/pages/sales-invoice.js',
                'resources/js/pages/stocks.js',
                'resources/js/pages/suppliers.js',
                'resources/js/pages/types.js',
                'resources/js/pages/units.js',
                'resources/js/pages/chart-of-accounts.js',
                'resources/js/pages/journal-entries.js',
                'resources/js/pages/opening-balances.js',
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