// ============================================================
// assets/react/app/layout/MainLayout/sidebar/sidebar.config.ts
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
//   - PATRON           : 7 entrées (Tableau de bord + 6 opérationnelles)
//   - ADMIN_IMMOBILIER : 6 entrées (sans Administration, AVEC Personnel :
//                        le backend lui accorde VIEW/CREATE/UPDATE/DELETE
//                        sur Worker et WorkerAssignment)
//   - ADMIN_VILLE      : 5 entrées (sans Administration ni Personnel :
//                        VIEW_WORKER seul ne justifie pas une entrée)
//   - SUPER_ADMIN      : 5 entrées, toutes au niveau plateforme
//
// Ce menu reflète la matrice `SecurityService::checkXxxAction()` : toute
// divergence entre une capacité réellement accordée par l'API et un item
// ici est un bug de UX (capacité inatteignable), pas un trou de sécurité.
//
// « Tableau de bord » est toujours la première entrée, pour chaque rôle :
// c'est l'écran de synthèse (KPI, occupation, loyers attendus/encaissés,
// impayés — cf. ReportController déjà en place) explicitement demandé par
// le cahier des charges d'origine (« le Patron suit son patrimoine à
// distance depuis son tableau de bord »). Il ne doit jamais être omis au
// profit d'un atterrissage direct sur une liste opérationnelle.
//
// ⚠️ Confinement : les seuls `if (role === …)` du front qui décident du
// menu vivent ici. La garde de route (`AppRouteGuard`) ne teste jamais le
// rôle non plus : elle demande au menu (`isPathInMenu`). Partout ailleurs,
// on consomme le tableau renvoyé par `resolveSidebar()` sans re-tester le
// rôle.
// ============================================================

import type { AppMenuItem, SidebarMenu } from './sidebar.types';
import type { OrganizationRole, PlatformRole } from '../../../../../services/api/api.types';

/**
 * Tableau de bord : toujours en premier. Une seule page par rôle, qui
 * consomme l'endpoint de rapport déjà résolu côté backend pour ce rôle
 * (patron/admin_immobilier/admin_ville) — pas de sous-menu : un tableau de
 * bord est une destination, pas une catégorie à déplier.
 */
const DASHBOARD: AppMenuItem = {
    id: 'dashboard',
    label: 'Tableau de bord',
    icon: 'gauge',
    path: '/app/dashboard',
};

const PATRIMOINE: AppMenuItem = {
    id: 'patrimoine',
    label: 'Patrimoine',
    icon: 'building',
    children: [
        { id: 'patrimoine-villes', label: 'Villes', path: '/app/patrimoine/villes' },
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

/**
 * Dépenses : feuille directe, plus de sous-menu « Finances > Dépenses /
 * Rapports ». « Rapports » a migré vers le Tableau de bord (c'est la même
 * donnée — pas de raison de la dupliquer à deux endroits du menu), ce qui
 * ne laissait plus qu'un seul enfant ici : un sous-menu à un seul item est
 * un clic inutile, donc on le supprime plutôt que de le garder « au cas où ».
 */
const DEPENSES: AppMenuItem = {
    id: 'depenses',
    label: 'Dépenses',
    icon: 'coins',
    path: '/app/depenses',
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
    icon: 'id-badge',
    children: [
        { id: 'administration-equipe', label: 'Équipe', path: '/app/administration/equipe' },
        { id: 'administration-villes', label: 'Villes assignées', path: '/app/administration/villes' },
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
 * - `admin_immobilier` : opérations + Personnel, qui est la 6e entrée
 *   (le backend lui accorde CREATE/UPDATE/DELETE sur Worker, cf.
 *   `SecurityService::checkAdminImmobilierAction`). Seule l'Administration
 *   reste réservée au PATRON.
 * - `admin_ville` : opérations sans Personnel (`VIEW_WORKER` seul : une
 *   entrée dédiée laisserait croire à des droits d'écriture) et sans
 *   Administration.
 */
export const ORGANIZATION_SIDEBAR: Record<OrganizationRole, SidebarMenu> = {
    patron: [DASHBOARD, PATRIMOINE, LOCATION, DEPENSES, VITRINE, PERSONNEL, ADMINISTRATION],
    admin_immobilier: [DASHBOARD, PATRIMOINE, LOCATION, DEPENSES, VITRINE, PERSONNEL],
    admin_ville: [DASHBOARD, PATRIMOINE, LOCATION, DEPENSES, VITRINE],
};

/** Menu SUPER_ADMIN : aucune entrée métier d'organisation. */
export const PLATFORM_SIDEBAR: SidebarMenu = [
    {
        id: 'plateforme-dashboard',
        label: 'Tableau de bord',
        icon: 'gauge',
        path: '/app/admin/dashboard',
    },
    {
        id: 'plateforme-organisations',
        label: 'Organisations',
        icon: 'briefcase',
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
 * La garde de route (`AppRouteGuard`) s'appuie exactement sur la même
 * vérité que le menu : une route non présente dans le menu du rôle courant
 * est hors de portée. Confort d'affichage uniquement — l'API reste l'autorité
 * de sécurité (une URL forgée sera de toute façon refusée en 403 par le
 * backend).
 */
export function isPathInMenu(menu: SidebarMenu, pathname: string): boolean {
    return menu.some(item => item.path === pathname
        || (item.children !== undefined && isPathInMenu(item.children, pathname)));
}

/**
 * Chemin d'atterrissage par défaut pour un rôle donné : toujours le
 * Tableau de bord quand il existe (première entrée de chaque menu), sinon
 * la première feuille rencontrée.
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
