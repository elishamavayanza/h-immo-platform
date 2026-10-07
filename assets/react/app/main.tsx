import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';

import { AuthProvider } from './providers/AuthProvider.tsx';
import { OrganizationProvider } from './providers/OrganizationProvider.tsx';
import { ToastProvider } from './layout/MainLayout/contexts/ToastContext.tsx';
import { AppRoutes } from './routes/AppRoutes.tsx';

// Le point d'entrée charge le thème global et toutes les primitives UI.
// Le reset reste séparé afin de conserver un ordre prévisible.
import '../../styles/base/_reset.scss';
import '../../styles/main.scss';

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
