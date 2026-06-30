import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig({
    resolve: {
        alias: {
            '@legacy': path.resolve(__dirname, 'public/js'),
        },
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/fullcalendar-v6.css',
                'resources/css/icons.css',
                'resources/js/app.js',
                'resources/js/lucide-init.js',
            ],
            refresh: true,
        }),
    ],
});
