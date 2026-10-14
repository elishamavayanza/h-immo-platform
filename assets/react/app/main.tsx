import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';

import { AuthProvider } from './providers/AuthProvider.tsx';
import { OrganizationProvider } from './providers/OrganizationProvider.tsx';
import { AdminCityProvider } from './providers/AdminCityProvider.tsx';
import { ToastProvider } from './layout/MainLayout/contexts/ToastContext.tsx';
import { AppRoutes } from './routes/AppRoutes.tsx';
import { initializeUserPreferences } from '../services/userPreferences';
import { ErrorBoundary } from '../components/UI/ErrorBoundary';
import { initializeClientErrorReporting } from '../services/clientErrorReporting';

// Le point d'entrée charge le thème global et toutes les primitives UI.
// Le reset reste séparé afin de conserver un ordre prévisible.
import '../../styles/base/_reset.scss';
import '../../styles/main.scss';

initializeUserPreferences();
initializeClientErrorReporting();

function App() {
    return (
        <ErrorBoundary>
            <BrowserRouter>
                <AuthProvider>
                    <OrganizationProvider>
                        <AdminCityProvider>
                            <ToastProvider>
                                <AppRoutes />
                            </ToastProvider>
                        </AdminCityProvider>
                    </OrganizationProvider>
                </AuthProvider>
            </BrowserRouter>
        </ErrorBoundary>
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
