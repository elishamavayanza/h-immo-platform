// ============================================================
// upload/react/app/routes/AppRoutes.tsx
// Table de routage du SPA.
//
// `react-router-dom` (v7) est installé et `main.tsx` monte un
// `<BrowserRouter>`. Deux familles de routes :
//
//   - Hors session (AuthLayout) : `/login`, `/forgot-password`, `/reset-password`.
//   - Back-office (`/app`, MainLayout) : sections chargées en
//     `React.lazy`, feuilles affichant un placeholder « à venir », garde
//     `RequireRole` (UX) positionnée par MainLayout.
//
// Les chemins des feuilles sont LES MÊMES que ceux du `sidebar.config` :
// si un chemin du menu disparaît, il disparaît aussi du routeur (et vice
// versa) — la garde `isPathInMenu` départage alors les rôles.
// ============================================================

import { lazy, Suspense } from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';

import { Loading } from '../../components/UI/Loading/Loading';
import { useOrganization } from '../providers/OrganizationProvider';
import { ForgotPasswordPage } from '../../features/auth/pages/ForgotPasswordPage';
import { LoginPage } from '../../features/auth/pages/LoginPage';
import { ResetPasswordPage } from '../../features/auth/pages/ResetPasswordPage';

import { AuthLayout } from '../layout/AuthLayout/AuthLayout';
import { MainLayout } from '../layout/MainLayout/MainLayout';
import { defaultPathFor } from '../layout/MainLayout/sidebar/sidebar.config';


/** `/app` → première feuille du menu du rôle courant (suivie par le sidebar). */
function AppLanding() {
    const { platformRole, organizationRole } = useOrganization();

    return <Navigate to={defaultPathFor(platformRole, organizationRole)} replace />;
}

export function AppRoutes() {
    return (
        <Suspense fallback={<Loading text="Chargement de la section..." />}>
            <Routes>
                <Route element={<AuthLayout />}>
                    <Route path="/login" element={<LoginPage />} />
                    <Route path="/forgot-password" element={<ForgotPasswordPage />} />
                    <Route path="/reset-password" element={<ResetPasswordPage />} />
                </Route>

                <Route path="/" element={<Navigate to="/app" replace />} />

                <Route path="/app" element={<MainLayout />}>
                    <Route index element={<AppLanding />} />

                </Route>

                <Route path="*" element={<Navigate to="/app" replace />} />
            </Routes>
        </Suspense>
    );
}
