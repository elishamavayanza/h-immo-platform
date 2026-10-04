// ============================================================
// assets/react/app/layout/MainLayout/RequireRole.tsx
// Garde de route D'AFFICHAGE (confort UX, aucune sécurité).
//
// Compare le `pathname` courant au menu résolu pour le rôle courant
// (`isPathInMenu`, sidebar.config). Une URL hors menu (ex. un ADMIN_VILLE
// qui accède à `/app/administration/equipe`) affiche « accès non prévu »
// au lieu d'une page blanche.
//
// ⚠️ Ce n'est JAMAIS une frontière de sécurité : le backend refuse de
// toute façon (403) toute ressource hors périmètre, quel que soit l'URL
// forgée par le client. Les seuls tests de rôle du front vivent ici et
// dans `sidebar.config.ts`.
// ============================================================

import type { ReactNode } from 'react';
import { Navigate, useLocation } from 'react-router-dom';

import { useOrganization } from '../../providers/OrganizationProvider';
import { isPathInMenu, resolveSidebar } from './sidebar/sidebar.config';

export function RequireRole({ children }: { children: ReactNode }) {
    const { pathname } = useLocation();
    const { platformRole, organizationRole } = useOrganization();

    // La page « accès non prévu » doit rester accessible pour afficher le
    // refus : elle échappe à la comparaison au menu.
    if (pathname === '/app/access-denied') {
        return <>{children}</>;
    }

    const menu = resolveSidebar(platformRole, organizationRole);

    if (isPathInMenu(menu, pathname)) {
        return <>{children}</>;
    }

    return <Navigate to="/app/access-denied" replace />;
}