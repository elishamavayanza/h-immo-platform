// ============================================================
// upload/react/app/routes/AppRoutes.tsx
// Table de routage du SPA.
// ============================================================
//
// `react-router-dom` (v7) est installé et `main.tsx` monte un
// `<BrowserRouter>`. Deux familles de routes :
//
//   - Hors session (AuthLayout) : `/login`, `/forgot-password`, `/reset-password`.
//   - Back-office (`/app`, MainLayout) : une route par entrée du menu
//     (menu plat), garde `AppRouteGuard`, et une page provisoire le temps
//     que les écrans métier arrivent. Le splat `entree/*` sert aussi les
//     URLs de drill-down plus profondes que l'entrée elle-même.
//
// Les chemins des feuilles ne sont PAS écrits à la main : ils sont dérivés
// de `sidebar.config.ts` par `buildAppRoutes()`. C'était la source du bug
// qui rendait le back-office inutilisable — aucune feuille n'était
// enregistrée, donc `/app` redirigeait vers une destination qui retombait
// sur `path="*"` qui redirigeait vers `/app`, en boucle. Un chemin ajouté au
// menu devient routable, et `tests/verify-sidebar-roles.ts` échoue si un
// lien du menu n'a pas de route ou si une destination d'atterrissage n'est
// pas routable.
// ============================================================

import { Navigate, Route, Routes, useNavigate } from 'react-router-dom';

import { ForgotPasswordPage } from '../../features/auth/pages/ForgotPasswordPage';
import { LoginPage } from '../../features/auth/pages/LoginPage';
import { ResetPasswordPage } from '../../features/auth/pages/ResetPasswordPage';

import { AuthLayout } from '../layout/AuthLayout/AuthLayout';
import { MainLayout } from '../layout/MainLayout/MainLayout';
import { PlaceholderPage } from '../pages/PlaceholderPage';
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

                    {APP_ROUTES.map((route) => (
                        <Route
                            key={route.path}
                            path={toRelativeAppRoutePath(route.path)}
                            element={<PlaceholderPage label={route.label} path={route.path} />}
                        />
                    ))}

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
