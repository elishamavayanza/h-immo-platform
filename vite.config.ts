import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [react()],
    root: 'assets/app',
    build: {
        outDir: '../../public/build',
        emptyOutDir: true,
    },
    // `appType: 'spa'` (valeur par défaut de Vite, rendue explicite) sert
    // index.html pour les routes inconnues : sans cela, un rechargement
    // direct sur /reset-password — ce que fait l'utilisateur en arrivant
    // sur le lien de l'email — renverrait un 404.
    appType: 'spa',
    server: {
        port: 5173,
        strictPort: true,
        // L'API Symfony n'a pas de bundle CORS : appelée depuis une autre
        // origine, elle serait bloquée par le navigateur. Le proxy évite
        // d'ajouter une dépendance serveur et garde le front sur une seule
        // origine, comme en production derrière un même nom de domaine.
        proxy: {
            '/api': {
                target: process.env.API_URL ?? 'http://127.0.0.1:8000',
                changeOrigin: false,
            },
        },
    },
});
