// ============================================================
// assets/react/app/routes/AppRoutes.tsx
// Table de routage du SPA.
// ============================================================

import { Navigate, Route, Routes, useNavigate } from 'react-router-dom';

import { ForgotPasswordPage } from '../../features/auth/pages/ForgotPasswordPage';
import { LoginPage } from '../../features/auth/pages/LoginPage';
import { ResetPasswordPage } from '../../features/auth/pages/ResetPasswordPage';
import { SuperAdminDashboardPage } from '../../features/super_admin/dashbord/pages/SuperAdminDashboardPage';
import { OrganizationsPage } from '../../features/super_admin/organizations/pages/OrganizationsPage';
import { UsersPage } from '../../features/super_admin/users/pages/UsersPage';
import { AuditLogPage } from '../../features/super_admin/audit/pages/AuditLogPage';
import { ExchangeRatesPage } from '../../features/super_admin/exchange_rates/pages/ExchangeRatesPage';
import { ReportsPage as SuperAdminReportsPage } from '../../features/super_admin/reports/pages/ReportsPage';
import { OrganizationMenuPage } from './OrganizationMenuPage';

import { AuthLayout } from '../layout/AuthLayout/AuthLayout';
import { MainLayout } from '../layout/MainLayout/MainLayout';
import { ErrorState } from '../../components/UI/ErrorState';
import { useOrganization } from '../providers/OrganizationProvider';
import { defaultPathFor } from '../layout/MainLayout/sidebar/sidebar.config';
import {
    ACCESS_DENIED_PATH,
    APP_ROOT,
    buildAppRoutes,
    toRelativeAppPath,
    toRelativeAppRoutePath,
} from './appRoutes.config';
import { AppRouteGuard } from './AppRouteGuard';

// Import des pages métier

/** Table figée au chargement : le menu est statique dans le bundle. */
const APP_ROUTES = buildAppRoutes();



/** `/app` → première feuille du menu du rôle courant (suivie par le sidebar). */
function AppLanding() {
    const { platformRole, organizationRole } = useOrganization();

    return <Navigate to={defaultPathFor(platformRole, organizationRole)} replace />;
}

/**
 * `/app/access-denied` : destination de `defaultPathFor()` quand aucun rôle
 * n'est résolu. Sans cette route, un compte sans rôle retombait sur `*`,
 * c'est-à-dire sur une boucle de redirection.
 */
function AccessDenied() {
    const navigate = useNavigate();
    const { platformRole, organizationRole } = useOrganization();
    const landingPath = defaultPathFor(platformRole, organizationRole);

    return (
        <ErrorState
            status={403}
            codeLabel="Forbidden"
            kind="forbidden"
            tone="danger"
            title="Accès refusé"
            message="Aucun espace ne vous est attribué. Contactez votre administrateur pour obtenir accès à une organisation."
            onBack={() => navigate(-1)}
            onHome={() => navigate(landingPath)}
        />
    );
}


export function AppRoutes() {
    return (
        <Routes>
            <Route element={<AuthLayout />}>
                <Route path="/login" element={<LoginPage />} />
                <Route path="/forgot-password" element={<ForgotPasswordPage />} />
                <Route path="/reset-password" element={<ResetPasswordPage />} />
            </Route>

            <Route path="/" element={<Navigate to={APP_ROOT} replace />} />

            <Route path={APP_ROOT} element={<MainLayout />}>
                {/* Route « transparente » : la garde statue sur le chemin,
                    puis le routeur choisit la feuille. */}
                <Route element={<AppRouteGuard />}>
                    <Route index element={<AppLanding />} />

                    {APP_ROUTES.map((route) => {
                        return (
                            <Route
                                key={route.path}
                                path={toRelativeAppRoutePath(route.path)}
                                element={
                                    route.path.startsWith('/app/admin/')
                                        ? route.path === '/app/admin/dashboard'
                                            ? <SuperAdminDashboardPage />
                                            : route.path === '/app/admin/organisations'
                                                ? <OrganizationsPage />
                                                : route.path === '/app/admin/utilisateurs'
                                                    ? <UsersPage />
                                                    : route.path === '/app/admin/audit'
                                                        ? <AuditLogPage />
                                                        : route.path === '/app/admin/taux-change'
                                                            ? <ExchangeRatesPage />
                                                            : route.path === '/app/admin/rapports'
                                                                ? <SuperAdminReportsPage />
                                                            : undefined
                                        : <OrganizationMenuPage path={route.path} />
                                }
                            />
                        );
                    })}

                    <Route
                        path={toRelativeAppPath(ACCESS_DENIED_PATH)}
                        element={<AccessDenied />}
                    />
                </Route>
            </Route>

            <Route path="*" element={<Navigate to={APP_ROOT} replace />} />
        </Routes>
    );
}
