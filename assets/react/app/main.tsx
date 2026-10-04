import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';

import { AuthProvider } from './providers/AuthProvider.tsx';
import { OrganizationProvider } from './providers/OrganizationProvider.tsx';
import { ToastProvider } from './layout/MainLayout/contexts/ToastContext.tsx';
import { AppRoutes } from './routes/AppRoutes.tsx';

// Partials du design system utilisés par les écrans (Sidebar, Dropdown,
// Avatar, Formulaires). `main.scss` (point d'entrée global du design system)
// n'est volontairement PAS branché : son ordre de `@use` ne compile pas tel
// quel (position des règles). Les pages de connexion/mot de passe chargent
// leur propre partial (`pages/auth/_auth-page.scss` + variantes).
import '../../styles/components/Navigation/_Sidebar.scss';
import '../../styles/components/UI/_Dropdown.scss';
import '../../styles/components/UI/_Avatar.scss';
import '../../styles/components/UI/_Loading.scss';
import '../../styles/components/UI/_Button.scss';
import '../../styles/components/UI/_Alert.scss';
import '../../styles/components/Form/_Form.scss';
import '../../styles/components/Form/_FormField.scss';
import '../../styles/components/Form/_Input.scss';
import '../../styles/components/Form/_Password.scss';

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