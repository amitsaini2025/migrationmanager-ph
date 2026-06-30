import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
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
