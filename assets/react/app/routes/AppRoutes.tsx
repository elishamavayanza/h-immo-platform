// ============================================================
// assets/react/app/routes/AppRoutes.tsx
// Table de routage du SPA.
// ============================================================

import { lazy, Suspense, type ComponentType } from 'react';
import { Navigate, Route, Routes, useNavigate } from 'react-router-dom';

import { AuthLayout } from '../layout/AuthLayout/AuthLayout';
import { MainLayout } from '../layout/MainLayout/MainLayout';
import { ErrorState } from '../../components/UI/ErrorState';
import { Spinner } from '../../components/UI/Spinner';
import { useOrganization } from '../providers/OrganizationProvider';
import { useAuth } from '../providers/AuthProvider';
import { defaultPathFor } from '../layout/MainLayout/sidebar/sidebar.config';
import {
    ACCESS_DENIED_PATH,
    APP_ROOT,
    buildAppRoutes,
    toRelativeAppPath,
    toRelativeAppRoutePath,
} from './appRoutes.config';
import { AppRouteGuard } from './AppRouteGuard';
import { readLastLocation } from './navigationMemory';

// Import des pages métier

/** Les écrans ne sont téléchargés qu'à l'ouverture de leur route. */
function lazyPage<Module extends object, ExportName extends keyof Module>(loader: () => Promise<Module>, exportName: ExportName) {
    return lazy(async () => ({ default: (await loader())[exportName] as ComponentType<any> }));
}

const LoginPage = lazyPage(() => import('../../features/auth/pages/LoginPage'), 'LoginPage');
const ForgotPasswordPage = lazyPage(() => import('../../features/auth/pages/ForgotPasswordPage'), 'ForgotPasswordPage');
const ResetPasswordPage = lazyPage(() => import('../../features/auth/pages/ResetPasswordPage'), 'ResetPasswordPage');
const SuperAdminDashboardPage = lazyPage(() => import('../../features/super_admin/dashboard/pages/SuperAdminDashboardPage'), 'SuperAdminDashboardPage');
const OrganizationsPage = lazyPage(() => import('../../features/super_admin/organizations/pages/OrganizationsPage'), 'OrganizationsPage');
const UsersPage = lazyPage(() => import('../../features/super_admin/users/pages/UsersPage'), 'UsersPage');
const AuditLogPage = lazyPage(() => import('../../features/super_admin/audit/pages/AuditLogPage'), 'AuditLogPage');
const SuperAdminReportsPage = lazyPage(() => import('../../features/super_admin/reports/pages/ReportsPage'), 'ReportsPage');
const PublicationsPage = lazyPage(() => import('../../features/super_admin/publications/pages/PublicationsPage'), 'PublicationsPage');
const PublicationDetailPage = lazyPage(() => import('../../features/shared/publications/pages/PublicationDetailPage'), 'PublicationDetailPage');
const OrganizationMenuPage = lazyPage(() => import('./OrganizationMenuPage'), 'OrganizationMenuPage');
const OrganisationPage = lazyPage(() => import('../../features/patron/administration/pages/OrganisationPage'), 'OrganisationPage');
const ProfilePage = lazyPage(() => import('../../features/shared/account/profile/pages/ProfilePage'), 'ProfilePage');
const SettingsPage = lazyPage(() => import('../../features/shared/account/settings/pages/SettingsPage'), 'SettingsPage');
const UserManualPage = lazyPage(() => import('../../features/shared/account/settings/pages/UserManualPage'), 'UserManualPage');

/** Table figée au chargement : le menu est statique dans le bundle. */
const APP_ROUTES = buildAppRoutes();



/** `/app` → première feuille du menu du rôle courant (suivie par le sidebar). */
function AppLanding() {
    const { platformRole, organizationRole } = useOrganization();
    const { user } = useAuth();
    const rememberedLocation = user ? readLastLocation(user.uuid, APP_ROUTES) : null;

    return <Navigate to={rememberedLocation ?? defaultPathFor(platformRole, organizationRole)} replace />;
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

    if (platformRole !== null || organizationRole !== null) {
        return <Navigate to={landingPath} replace />;
    }

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
    const pageFallback = <div className="main-layout__page-loading" aria-busy="true"><Spinner size="large" className="spinner--page" /><span>Chargement de la page…</span></div>;

    return (
        <Routes>
            <Route element={<AuthLayout />}>
                <Route path="/login" element={<Suspense fallback={pageFallback}><LoginPage /></Suspense>} />
                <Route path="/forgot-password" element={<Suspense fallback={pageFallback}><ForgotPasswordPage /></Suspense>} />
                <Route path="/reset-password" element={<Suspense fallback={pageFallback}><ResetPasswordPage /></Suspense>} />
            </Route>

            <Route path="/" element={<Navigate to={APP_ROOT} replace />} />

            <Route path={APP_ROOT} element={<MainLayout />}>
                {/* Route « transparente » : la garde statue sur le chemin,
                    puis le routeur choisit la feuille. */}
                <Route element={<AppRouteGuard />}>
                    <Route index element={<AppLanding />} />

                    <Route path="publications/:uuid" element={<Suspense fallback={pageFallback}><PublicationDetailPage /></Suspense>} />

{APP_ROUTES.map((route) => {
                        return (
                            <Route
                                key={route.path}
                                path={toRelativeAppRoutePath(route.path)}
                                element={
                                    route.path.startsWith('/app/admin/')
                                        ? route.path === '/app/admin/dashboard'
                                            ? <Suspense fallback={pageFallback}><SuperAdminDashboardPage /></Suspense>
                                            : route.path === '/app/admin/organisations'
                                                ? <Suspense fallback={pageFallback}><OrganizationsPage /></Suspense>
                                                : route.path === '/app/admin/utilisateurs'
                                                    ? <Suspense fallback={pageFallback}><UsersPage /></Suspense>
                                                    : route.path === '/app/admin/audit'
                                                        ? <Suspense fallback={pageFallback}><AuditLogPage /></Suspense>
                                                        : route.path === '/app/admin/rapports'
                                                            ? <Suspense fallback={pageFallback}><SuperAdminReportsPage /></Suspense>
                                                            : route.path === '/app/admin/publications'
                                                                ? <Suspense fallback={pageFallback}><PublicationsPage /></Suspense>
                                                            : undefined
                                        : <Suspense fallback={pageFallback}><OrganizationMenuPage path={route.path} /></Suspense>
                                }
                            />
                        );
                    })}

                    <Route path="organisation" element={<Suspense fallback={pageFallback}><OrganisationPage /></Suspense>} />

                    <Route
                        path={toRelativeAppPath(ACCESS_DENIED_PATH)}
                        element={<AccessDenied />}
                    />
                </Route>
            </Route>

            {/* Pages ouvertes depuis le menu utilisateur du pied de sidebar. */}
            <Route element={<MainLayout />}>
                <Route path="/profile" element={<Suspense fallback={pageFallback}><ProfilePage /></Suspense>} />
                <Route path="/settings" element={<Suspense fallback={pageFallback}><SettingsPage /></Suspense>} />
                <Route path="/settings/organisation" element={<Suspense fallback={pageFallback}><SettingsPage /></Suspense>} />
                <Route path="/settings/manuel" element={<Suspense fallback={pageFallback}><UserManualPage /></Suspense>} />
            </Route>

            <Route path="*" element={<Navigate to={APP_ROOT} replace />} />
        </Routes>
    );
}
