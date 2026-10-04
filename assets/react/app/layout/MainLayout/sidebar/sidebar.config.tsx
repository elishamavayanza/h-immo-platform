// ============================================================
// assets/react/app/layout/MainLayout/sidebar/sidebar.config.tsx
// Configuration déclarative du menu latéral, PAR RÔLE.
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
//   - PATRON          : 6 entrées avec sous-menus
//   - ADMIN_IMMOBILIER: 4 entrées (sans Administration ni Personnel)
//   - ADMIN_VILLE     : 4 entrées (sans Administration ni Personnel)
//   - SUPER_ADMIN     : aucune entrée métier d'organisation
//
// ⚠️ Confinement : les seuls `if (role === …)` du front qui décident du
// menu et de la garde de route vivent ici (et dans RequireRole, qui
// appelle `isPathInMenu`). Partout ailleurs, on consomme le tableau
// renvoyé par `resolveSidebar()` sans re-tester le rôle.
// ============================================================

import type { AppMenuItem, SidebarMenu } from './sidebar.types';
import type { OrganizationRole, PlatformRole } from '../../../../../services/api/api.types';
import {
    IconBuilding,
    IconKey,
    IconCoins,
    IconStorefront,
    IconGear,
    IconHardHat,
    IconUsers,
    IconOrganizations,
    IconAudit,
    IconExchange,
} from './sidebar.icons';

const PATRIMOINE: AppMenuItem = {
    id: 'patrimoine',
    label: 'Patrimoine',
    icon: <IconBuilding />,
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
    icon: <IconKey />,
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
    icon: <IconCoins />,
    children: [
        { id: 'finances-depenses', label: 'Dépenses', path: '/app/finances/depenses' },
        { id: 'finances-rapports', label: 'Rapports', path: '/app/finances/rapports' },
    ],
};

/** Vitrine publique : feuille sans sous-menu (vitrine/page publique à venir). */
const VITRINE: AppMenuItem = {
    id: 'vitrine',
    label: 'Vitrine',
    icon: <IconStorefront />,
    path: '/app/vitrine',
};

const ADMINISTRATION: AppMenuItem = {
    id: 'administration',
    label: 'Administration',
    icon: <IconGear />,
    children: [
        { id: 'administration-equipe', label: 'Équipe', path: '/app/administration/equipe' },
        { id: 'administration-villes', label: 'Villes', path: '/app/administration/villes' },
    ],
};

const PERSONNEL: AppMenuItem = {
    id: 'personnel',
    label: 'Personnel',
    icon: <IconHardHat />,
    children: [
        { id: 'personnel-ouvriers', label: 'Ouvriers', path: '/app/personnel/ouvriers' },
        { id: 'personnel-affectations', label: 'Affectations', path: '/app/personnel/affectations' },
    ],
};

/**
 * Menu des rôles métier d'organisation, par rôle.
 *
 * `admin_immobilier` et `admin_ville` partagent les entrées opérationnelles
 * (Patrimoine, Location, Finances, Vitrine) et ne voient NI Administration
 * NI Personnel : ces deux sections sont réservées au PATRON.
 */
export const ORGANIZATION_SIDEBAR: Record<OrganizationRole, SidebarMenu> = {
    patron: [PATRIMOINE, LOCATION, FINANCES, VITRINE, ADMINISTRATION, PERSONNEL],
    admin_immobilier: [PATRIMOINE, LOCATION, FINANCES, VITRINE],
    admin_ville: [PATRIMOINE, LOCATION, FINANCES, VITRINE],
};

/** Menu SUPER_ADMIN : aucune entrée métier d'organisation. */
export const PLATFORM_SIDEBAR: SidebarMenu = [
    {
        id: 'plateforme-organisations',
        label: 'Organisations',
        icon: <IconOrganizations />,
        path: '/app/admin/organisations',
    },
    {
        id: 'plateforme-utilisateurs',
        label: 'Utilisateurs',
        icon: <IconUsers />,
        path: '/app/admin/utilisateurs',
    },
    {
        id: 'plateforme-audit',
        label: 'Journal d\'audit',
        icon: <IconAudit />,
        path: '/app/admin/audit',
    },
    {
        id: 'plateforme-taux-change',
        label: 'Taux de change',
        icon: <IconExchange />,
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