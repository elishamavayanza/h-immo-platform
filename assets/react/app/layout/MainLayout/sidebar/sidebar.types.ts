// ============================================================
// assets/react/app/layout/MainLayout/sidebar/sidebar.types.ts
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
 * La configuration est PLATE (cf. `sidebar.config.ts`) : chaque item porte
 * un `path` et un seul, et `children` n'est pas utilisé dans le menu — les
 * vues « filles » d'une relation 1─N sont du drill-down DANS la page portée
 * par `path`, pas des niveaux de menu. La propriété `children` est
 * conservée dans le type pour les arbres de test purs et la compatibilité
 * d'un futur menu dès lors qu'une destination prouverait qu'elle se consulte
 * indépendamment de son parent.
 *
 * - `path` : URL cible de l'entrée, qui couvre aussi ses drill-down
 *   (`/app/patrimoine` couvre `/app/patrimoine/villes/12/parcelles/4`).
 * - `children` : sous-menu (non utilisé aujourd'hui, limite 2 niveaux).
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
    /** Clé de regroupement visuel du menu latéral. */
    section?: 'overview' | 'property' | 'finance' | 'operations' | 'administration' | 'platform';
    children?: AppMenuItem[];
}

/** Configuration complète d'un menu : une liste d'items de premier niveau. */
export type SidebarMenu = AppMenuItem[];
