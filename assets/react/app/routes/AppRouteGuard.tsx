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
import { Navigate, Outlet, useLocation, useNavigate } from 'react-router-dom';

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
    const { platformRole, organizationRole, currentOrganization } = useOrganization();
    const location = useLocation();
    const navigate = useNavigate();
    const landingPath = defaultPathFor(platformRole, organizationRole);
    const routeContent = children ?? <Outlet />;
    const deniedRoute = findAppRoute(location.pathname);
    const roleLabel = organizationRole === 'admin_ville'
        ? 'Administrateur de ville'
        : organizationRole === 'admin_immobilier'
            ? 'Administrateur immobilier'
            : organizationRole === 'patron'
                ? 'Patron'
                : null;

    // 1. Points d'entrée du back-office, hors menu.
    if (location.pathname === APP_ROOT || location.pathname === ACCESS_DENIED_PATH) {
        return <>{routeContent}</>;
    }

    // La page de détail est ouverte depuis le centre global de notifications.
    // L'API vérifie le destinataire exact avant de renvoyer ou marquer la lecture.
    if (/^\/app\/publications\/[0-9a-f-]{36}$/i.test(location.pathname)) {
        return <>{routeContent}</>;
    }

    // 2. Feuille du rôle courant.
    if (isPathInMenu(resolveSidebar(platformRole, organizationRole, currentOrganization?.showcaseEnabled === true), location.pathname)) {
        return <>{routeContent}</>;
    }

    // 3. Feille existante mais hors périmètre du rôle → 403.
    if (deniedRoute !== undefined) {
        const title = platformRole === 'super_admin'
            ? 'Page réservée à une organisation'
            : 'Accès non inclus dans votre rôle';
        const message = platformRole === 'super_admin'
            ? `Vous êtes connecté à l’espace Super Administrateur. « ${deniedRoute.label} » appartient à l’espace de gestion d’une organisation. Pour y accéder, utilisez un compte ayant le rôle Patron, Administrateur immobilier ou Administrateur de ville dans cette organisation.`
            : roleLabel
                ? `Le rôle ${roleLabel} ne permet pas d’ouvrir « ${deniedRoute.label} ». Si vous pensez qu’il s’agit d’une erreur, demandez à l’administrateur de vérifier votre rôle dans l’organisation active.`
                : `Aucun rôle d’organisation actif ne permet d’ouvrir « ${deniedRoute.label} ». Sélectionnez une organisation avec un accès autorisé.`;

        return (
            <ErrorState
                status={403}
                codeLabel="Forbidden"
                kind="forbidden"
                tone="danger"
                title={title}
                message={message}
                onBack={() => navigate(-1)}
                onHome={() => navigate(landingPath)}
            />
        );
    }

    // 4. Chemin inconnu → atterrissage du rôle.
    return <Navigate to={landingPath} replace />;
}
