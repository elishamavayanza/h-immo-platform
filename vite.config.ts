import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
    plugins: [react()],

    root: 'assets/react/app',

    publicDir: path.resolve(__dirname, 'assets/react/app/public'),

    build: {
        outDir: path.resolve(__dirname, 'public/build'),
        emptyOutDir: true,
    },

    appType: 'spa',

    server: {
        port: 5173,
        strictPort: true,

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
