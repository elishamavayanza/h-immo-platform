// ============================================================
// upload/react/app/layout/MainLayout/sidebar/sidebar.types.ts
// Types du menu latéral piloté par configuration.
//
// Le menu n'est qu'un confort d'affichage : il reflète le rôle
// RÉSOLU côté backend pour l'Organization active (cf. endpoint
// `GET /api/v1/identity/organizations/{uuid}/membership`). Il
// ne porte aucune décision de sécurité : chaque route conserve
// son propre contrôle côté API.
// ============================================================

import type { OrganizationRole, PlatformRole } from '../../../../../services/api/api.types';

/** Rôle d'un item de menu : rôle métier d'organisation ou rôle plateforme. */
export type SidebarRole = OrganizationRole | PlatformRole;

/**
 * Item de menu.
 *
 * - `path` : URL cible (feuille). Un parent n'a pas de `path` : il est
 *   ouvrant.
 * - `children` : sous-menu (2 niveaux au maximum : première entrée =
 *   niveau 1, `children` = niveau 2, plus de `children` ensuite).
 */
export interface AppMenuItem {
    id: string;
    label: string;
    /**
     * Nom de l'icône, résolu par `SIDEBAR_ICON_MAP` au rendu.
     *
     * La configuration vit dans un module pur (sans JSX) pour être
     * exécutable telle quelle par lecture (`node`), comme les autres
     * scripts de logique front pure ; le JSX d'icônes reste dans
     * `sidebar.icons.tsx`.
     */
    icon?: string;
    path?: string;
    children?: AppMenuItem[];
}

/** Configuration complète d'un menu : une liste d'items de premier niveau. */
export type SidebarMenu = AppMenuItem[];
