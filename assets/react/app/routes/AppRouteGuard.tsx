// ============================================================
// assets/react/app/routes/AppRouteGuard.tsx
// Garde de route du back-office, posée sur toutes les feuilles `/app`.
// ============================================================
//
// Trois verdicts, du plus permissif au plus strict :
//
//   1. `/app` et `/app/access-denied`  → toujours rendues ;
//   2. chemin présent dans le menu du rôle courant → rendue ;
//   3. chemin connu mais réservé à un AUTRE rôle → 403 explicite
//      (et non une redirection silencieuse : l'utilisateur doit comprendre
//      qu'on lui a refusé la page, pas qu'il s'est trompé d'URL) ;
//   4. chemin inconnu → retour à l'atterrissage du rôle. Cette
//      destination est TOUJOURS routée (`buildLandingPaths()` le vérifie
//      dans `tests/verify-sidebar-roles.ts`), c'est ce qui interdit la
//      boucle `/app` → destination → `*` → `/app`.
//
// La même vérité que le menu : `isPathInMenu(menu, path)`. Le rôle vient
// de `useOrganization()`, lui-même alimenté par l'endpoint
// `GET /api/v1/identity/organizations/{uuid}/membership`.
//
// ⚠️ Confort d'UX, PAS une sécurité. Une URL forgée affiche un 403 ici mais
// le backend refusera de son côté l'appel API : `SecurityService` reste
// l'autorité (cf. AGENTS.md §5).
//
// Pas de branche « chargement » : `MainLayout` bloque déjà sur
// `isAuthLoading`/`isOrgLoading` avant de rendre son `<Outlet />`, donc le
// rôle est résolu quand ce composant s'exécute.
// ============================================================

import type { ReactNode } from 'react';
import { Navigate, useLocation, useNavigate } from 'react-router-dom';

import { ErrorState } from '../../components/UI/ErrorState';
import { useOrganization } from '../providers/OrganizationProvider';
import {
    defaultPathFor,
    isPathInMenu,
    resolveSidebar,
} from '../layout/MainLayout/sidebar/sidebar.config';
import {
    ACCESS_DENIED_PATH,
    APP_ROOT,
    findAppRoute,
} from './appRoutes.config';

export interface AppRouteGuardProps {
    children?: ReactNode;
}

export function AppRouteGuard({ children }: AppRouteGuardProps) {
    const { platformRole, organizationRole } = useOrganization();
    const location = useLocation();
    const navigate = useNavigate();
    const landingPath = defaultPathFor(platformRole, organizationRole);

    // 1. Points d'entrée du back-office, hors menu.
    if (location.pathname === APP_ROOT || location.pathname === ACCESS_DENIED_PATH) {
        return <>{children}</>;
    }

    // 2. Feuille du rôle courant.
    if (isPathInMenu(resolveSidebar(platformRole, organizationRole), location.pathname)) {
        return <>{children}</>;
    }

    // 3. Feille existante mais hors périmètre du rôle → 403.
    if (findAppRoute(location.pathname) !== undefined) {
        return (
            <ErrorState
                status={403}
                codeLabel="Forbidden"
                kind="forbidden"
                tone="danger"
                title="Accès refusé"
                message="Cette page n’est pas disponible pour votre rôle. Si vous pensez qu’il s’agit d’une erreur, contactez votre administrateur."
                onBack={() => navigate(-1)}
                onHome={() => navigate(landingPath)}
            />
        );
    }

    // 4. Chemin inconnu → atterrissage du rôle.
    return <Navigate to={landingPath} replace />;
}
