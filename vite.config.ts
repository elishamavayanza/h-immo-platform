import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [react()],

    root: 'assets/react/app',

    build: {
        outDir: '../../public/build',
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
        },
    },
});
