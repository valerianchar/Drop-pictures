import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
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
        tailwindcss(),
    ],
    server: {
        /*
         * Le serveur de développement tourne dans le conteneur Sail : il écoute sur
         * toutes les interfaces, et sur le port publié par compose (VITE_PORT du .env).
         * Le HMR, lui, s'adresse au navigateur, donc à localhost.
         */
        host: '0.0.0.0',
        port: 5174,
        strictPort: true,
        hmr: { host: 'localhost' },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
