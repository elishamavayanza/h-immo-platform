import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';

import { AuthProvider } from './providers/AuthProvider.tsx';
import { OrganizationProvider } from './providers/OrganizationProvider.tsx';
import { ToastProvider } from './layout/MainLayout/contexts/ToastContext.tsx';
import { AppRoutes } from './routes/AppRoutes.tsx';

// Styles de la page de réinitialisation (indépendante) + partials du design
// system utilisés par le back-office (Sidebar, Dropdown, Avatar). `main.scss`
// (point d'entrée global du design system) n'est volontairement PAS branché :
// son ordre de `@use` ne compile pas tel quel (position des règles).
import './styles.css';
import '../../styles/components/Navigation/_Sidebar.scss';
import '../../styles/components/UI/_Dropdown.scss';
import '../../styles/components/UI/_Avatar.scss';
import '../../styles/components/UI/_Loading.scss';

function App() {
    return (
        <BrowserRouter>
            <AuthProvider>
                <OrganizationProvider>
                    <ToastProvider>
                        <AppRoutes />
                    </ToastProvider>
                </OrganizationProvider>
            </AuthProvider>
        </BrowserRouter>
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