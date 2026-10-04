// ============================================================
// upload/react/app/layout/MainLayout/sidebar/sidebar.config.ts
// Configuration déclarative du menu latéral, PAR RÔLE.
//
// Module PUR (ni React ni JSX) : la logique `resolveSidebar` /
// `isPathInMenu` / `defaultPathFor` est exécutable par Node
// (`tests/verify-sidebar-roles.ts`). Les icônes sont référencées par
// NOM (string) et résolues au rendu via `SIDEBAR_ICON_MAP` dans
// `sidebar.icons.tsx`.
//
// Deux familles :
//   - PLATFORM_SIDEBAR        : menu SUPER_ADMIN (plateforme)
//   - ORGANIZATION_SIDEBAR    : un menu par rôle métier d'organisation
//                               (PATRON, ADMIN_IMMOBILIER, ADMIN_VILLE)
//
// `resolveSidebar()` est LE point d'entrée unique : il choisit le menu
// d'après le rôle PLATEFORME puis, à défaut, le rôle métier résolu pour
// l'Organization ACTIVE (jamais un rôle global de compte).
//
// Règle d'affichage (acceptance) :
//   - PATRON           : 6 entrées avec sous-menus
//   - ADMIN_IMMOBILIER : 5 entrées (sans Administration, AVEC Personnel :
//                        le backend lui accorde VIEW/CREATE/UPDATE/DELETE
//                        sur Worker et WorkerAssignment)
//   - ADMIN_VILLE      : 4 entrées (sans Administration ni Personnel :
//                        VIEW_WORKER seul ne justifie pas une entrée)
//   - SUPER_ADMIN      : aucune entrée métier d'organisation
//
// Ce menu reflète la matrice `SecurityService::checkXxxAction()` : toute
// divergence entre une capacité réellement accordée par l'API et un item
// ici est un bug de UX (capacité inatteignable), pas un trou de sécurité.
//
// ⚠️ Confinement : les seuls `if (role === …)` du front qui décident du
// menu et de la garde de route vivent ici (et dans RequireRole, qui
// appelle `isPathInMenu`). Partout ailleurs, on consomme le tableau
// renvoyé par `resolveSidebar()` sans re-tester le rôle.
// ============================================================

import type { AppMenuItem, SidebarMenu } from './sidebar.types';
import type { OrganizationRole, PlatformRole } from '../../../../../services/api/api.types';

const PATRIMOINE: AppMenuItem = {
    id: 'patrimoine',
    label: 'Patrimoine',
    icon: 'building',
    children: [
        { id: 'patrimoine-villes', label: 'Cités', path: '/app/patrimoine/villes' },
        { id: 'patrimoine-parcelles', label: 'Parcelles', path: '/app/patrimoine/parcelles' },
        { id: 'patrimoine-batiments', label: 'Bâtiments', path: '/app/patrimoine/batiments' },
        { id: 'patrimoine-unites', label: 'Unités', path: '/app/patrimoine/unites' },
    ],
};

const LOCATION: AppMenuItem = {
    id: 'location',
    label: 'Location',
    icon: 'key',
    children: [
        { id: 'location-locataires', label: 'Locataires', path: '/app/location/locataires' },
        { id: 'location-baux', label: 'Baux', path: '/app/location/baux' },
        { id: 'location-loyers', label: 'Loyers', path: '/app/location/loyers' },
        { id: 'location-paiements', label: 'Paiements', path: '/app/location/paiements' },
    ],
};

const FINANCES: AppMenuItem = {
    id: 'finances',
    label: 'Finances',
    icon: 'coins',
    children: [
        { id: 'finances-depenses', label: 'Dépenses', path: '/app/finances/depenses' },
        { id: 'finances-rapports', label: 'Rapports', path: '/app/finances/rapports' },
    ],
};

/** Vitrine publique : feuille sans sous-menu (vitrine/page publique à venir). */
const VITRINE: AppMenuItem = {
    id: 'vitrine',
    label: 'Vitrine',
    icon: 'storefront',
    path: '/app/vitrine',
};

const ADMINISTRATION: AppMenuItem = {
    id: 'administration',
    label: 'Administration',
    icon: 'gear',
    children: [
        { id: 'administration-equipe', label: 'Équipe', path: '/app/administration/equipe' },
        { id: 'administration-villes', label: 'Villes', path: '/app/administration/villes' },
    ],
};

const PERSONNEL: AppMenuItem = {
    id: 'personnel',
    label: 'Personnel',
    icon: 'hard-hat',
    children: [
        { id: 'personnel-ouvriers', label: 'Ouvriers', path: '/app/personnel/ouvriers' },
        { id: 'personnel-affectations', label: 'Affectations', path: '/app/personnel/affectations' },
    ],
};

/**
 * Menu des rôles métier d'organisation, par rôle.
 *
 * - `patron` : tout le menu.
 * - `admin_immobilier` : opérations + Personnel, qui est la 5e entrée
 *   (le backend lui accorde CREATE/UPDATE/DELETE sur Worker, cf.
 *   `SecurityService::checkAdminImmobilierAction`). Seule l'Administration
 *   reste réservée au PATRON.
 * - `admin_ville` : opérations sans Personnel (`VIEW_WORKER` seul : une
 *   entrée dédiée laisserait croire à des droits d'écriture) et sans
 *   Administration.
 */
export const ORGANIZATION_SIDEBAR: Record<OrganizationRole, SidebarMenu> = {
    patron: [PATRIMOINE, LOCATION, FINANCES, VITRINE, ADMINISTRATION, PERSONNEL],
    admin_immobilier: [PATRIMOINE, LOCATION, FINANCES, VITRINE, PERSONNEL],
    admin_ville: [PATRIMOINE, LOCATION, FINANCES, VITRINE],
};

/** Menu SUPER_ADMIN : aucune entrée métier d'organisation. */
export const PLATFORM_SIDEBAR: SidebarMenu = [
    {
        id: 'plateforme-organisations',
        label: 'Organisations',
        icon: 'organizations',
        path: '/app/admin/organisations',
    },
    {
        id: 'plateforme-utilisateurs',
        label: 'Utilisateurs',
        icon: 'users',
        path: '/app/admin/utilisateurs',
    },
    {
        id: 'plateforme-audit',
        label: 'Journal d\'audit',
        icon: 'audit',
        path: '/app/admin/audit',
    },
    {
        id: 'plateforme-taux-change',
        label: 'Taux de change',
        icon: 'exchange',
        path: '/app/admin/taux-change',
    },
];

/**
 * Résout le menu d'un utilisateur à partir de ses rôles.
 *
 * Ordre des priorités :
 *   1. `platformRole === 'super_admin'`  →  menu plateforme (le sélecteur
 *      d'organisation métier est absent pour lui).
 *   2. `organizationRole` résolu pour l'Organization ACTIVE  →  menu de ce
 *      rôle. Un utilisateur multi-organisation porte un rôle DIFFÉRENT par
 *      organisation : on n'utilise jamais un rôle « global ».
 *   3. `organizationRole` absent (plus d'appartenance valide)  →  menu vide.
 */
export function resolveSidebar(platformRole: PlatformRole | null, organizationRole: OrganizationRole | null): SidebarMenu {
    if (platformRole === 'super_admin') {
        return PLATFORM_SIDEBAR;
    }

    if (!organizationRole) {
        return [];
    }

    return ORGANIZATION_SIDEBAR[organizationRole] ?? [];
}

/**
 * Vrai si `pathname` correspond à un item (feuille ou sous-item) du menu.
 *
 * La garde de route (RequireRole) s'appuie exactement sur la même vérité
 * que le menu : une route non présente dans le menu du rôle courant est
 * hors de portée. Confort d'affichage uniquement — l'API reste l'autorité
 * de sécurité (une URL forgée sera de toute façon refusée en 403 par le
 * backend).
 */
export function isPathInMenu(menu: SidebarMenu, pathname: string): boolean {
    return menu.some(item => item.path === pathname
        || (item.children !== undefined && isPathInMenu(item.children, pathname)));
}

/**
 * Chemin d'atterrissage par défaut pour un rôle donné : première feuille
 * du menu résolu (utilisé pour la redirection `/app` → première section).
 */
export function defaultPathFor(platformRole: PlatformRole | null, organizationRole: OrganizationRole | null): string {
    const menu = resolveSidebar(platformRole, organizationRole);

    for (const item of menu) {
        if (item.path) return item.path;

        if (item.children?.length) {
            for (const child of item.children) {
                if (child.path) return child.path;
            }
        }
    }

    return '/app/access-denied';
}
