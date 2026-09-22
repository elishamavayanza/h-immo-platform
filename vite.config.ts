import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [react()],
    root: 'assets/app',
    build: {
        outDir: '../../public/build',
        emptyOutDir: true,
    },
    server: {
        port: 5173,
        strictPort: true,
    },
});
