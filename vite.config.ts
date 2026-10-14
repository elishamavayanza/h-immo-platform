import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const projectRoot = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig({
    plugins: [react()],

    root: 'assets/react/app',

    publicDir: path.resolve(projectRoot, 'assets/react/app/public'),

    build: {
        outDir: path.resolve(projectRoot, 'public/build'),
        emptyOutDir: true,
    },

    appType: 'spa',

    server: {
        port: 5173,
        strictPort: true,
        // Le point d'entrée Vite est dans assets/react/app, tandis que les
        // fonctionnalités importées vivent dans assets/react/features.
        // Autoriser explicitement la racine du dépôt évite que le serveur
        // refuse une nouvelle fonctionnalité hors du root de l'application.
        fs: {
            allow: [projectRoot],
        },

        proxy: {
            '/api': {
                target: process.env.API_URL ?? 'http://127.0.0.1:8000',
                changeOrigin: false,
            },
            '/uploads': {
                target: process.env.API_URL ?? 'http://127.0.0.1:8000',
                changeOrigin: false,
            },
        },
    },
});
