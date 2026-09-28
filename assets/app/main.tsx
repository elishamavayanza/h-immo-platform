import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { ResetPasswordPage } from './ResetPasswordPage';
import './styles.css';

/**
 * Le projet n'embarque pas encore de routeur : le point d'entrée décide donc
 * lui-même de la page à afficher d'après le chemin de l'URL. Le serveur de
 * développement Vite sert `index.html` pour toute route inconnue
 * (`appType: 'spa'`, son comportement par défaut), ce qui permet à
 * `/reset-password` de fonctionner sans configuration supplémentaire.
 */
function App() {
    const { pathname, search } = window.location;

    if (pathname === '/reset-password') {
        return <ResetPasswordPage search={search} />;
    }

    return (
        <div>
            <h1>Gestion H-Immo</h1>
            <p>Frontend React 19 + TypeScript</p>
        </div>
    );
}

const rootElement = document.getElementById('root');

if (!rootElement) {
    throw new Error('Root element #root not found');
}

createRoot(rootElement).render(
    <StrictMode>
        <App />
    </StrictMode>,
);
