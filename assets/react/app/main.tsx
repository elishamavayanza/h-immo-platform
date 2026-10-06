import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';

import { AuthProvider } from './providers/AuthProvider.tsx';
import { OrganizationProvider } from './providers/OrganizationProvider.tsx';
import { ToastProvider } from './layout/MainLayout/contexts/ToastContext.tsx';
import { AppRoutes } from './routes/AppRoutes.tsx';

// Partials de style chargés dans l'ordre : reset navigateur d'abord,
// puis les partials du design system utilisés par les écrans (Sidebar,
// Dropdown, Avatar, Formulaires). `main.scss` (point d'entrée global du
// design system) n'est volontairement PAS branché : son ordre de `@use`
// ne compile pas tel quel (position des règles). Les écrans
// d'authentification chargent leur socle `pages/auth/_auth-page.scss`
// via les partials de page importés ici.
import '../../styles/base/_reset.scss';
import '../../styles/components/Navigation/_Sidebar.scss';
// Sous-menu flottant du rail : sans cet import, le panneau s'affiche en texte
// brut au-dessus du contenu, sans fond, sans bordure et sans confines.
import '../../styles/components/Navigation/_SidebarFlyout.scss';
// Bulle du nom de menu au survol du rail : sans cet import, la bulle
// (remplaçant l'infobulle native `title`) s'affiche sans style.
import '../../styles/components/Navigation/_SidebarItemTooltip.scss';
import '../../styles/components/UI/_Dropdown.scss';
// Sans cet import, <PopoverMenu /> (menu utilisateur du footer du sidebar)
// rendait une liste sans aucun style : ni panneau, ni survol, ni focus.
import '../../styles/components/UI/_PopoverMenu.scss';
import '../../styles/components/UI/_Avatar.scss';
import '../../styles/components/UI/_Loading.scss';
// `<ErrorState />` sert aux écrans 403 du back-office (`AppRouteGuard`,
// `/app/access-denied`) : sans ce partial, ces 403 s'affichaient sans
// aucun style.
import '../../styles/components/UI/_ErrorState.scss';
import '../../styles/components/UI/_Button.scss';
import '../../styles/components/UI/_Alert.scss';
import '../../styles/components/Form/_Form.scss';
import '../../styles/components/Form/_FormField.scss';
import '../../styles/components/Form/_Input.scss';
import '../../styles/components/Form/_Password.scss';
import '../../styles/pages/auth/_login.scss';
import '../../styles/pages/auth/_forgot-password.scss';
import '../../styles/pages/auth/_reset-password.scss';

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
