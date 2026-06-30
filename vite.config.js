import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig({
    resolve: {
        alias: {
            '@legacy': path.resolve(__dirname, 'public/js'),
            '@legacy-css': path.resolve(__dirname, 'public/css'),
            jquery: path.resolve(__dirname, 'resources/js/vendor/jquery-global-shim.js'),
        },
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/fullcalendar-v6.css',
                'resources/css/icons.css',
                'resources/css/vendor-libs.css',
                'resources/js/app.js',
                'resources/js/lucide-init.js',
                'resources/js/vendor-libs.js',
                'resources/js/vendor-pdfmake.js',
                'resources/js/layouts/crm-layout-shared.js',
                'resources/js/layouts/crm-echo-broadcasts.js',
            ],
            refresh: true,
        }),
    ],
});
