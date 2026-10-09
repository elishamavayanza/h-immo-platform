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
//   - PATRON           : 8 entrées (Personnel fusionné dans Administration :
//                        « Équipe » et « Personnel » désignent la même
//                        réalité — une seule liste, pas d'onglets)
//   - ADMIN_IMMOBILIER : 8 entrées (sans Administration, AVEC Personnel :
//                        le backend lui accorde VIEW/CREATE/UPDATE/DELETE
//                        sur Worker et WorkerAssignment)
//   - ADMIN_VILLE      : 7 entrées (sans Administration ni Personnel :
//                        VIEW_WORKER seul ne justifie pas une entrée)
//   - SUPER_ADMIN      : 5 entrées, toutes au niveau plateforme
//
// Ce menu reflète la matrice `SecurityService::checkXxxAction()` : toute
// divergence entre une capacité réellement accordée par l'API et un item
// ici est un bug de UX (capacité inatteignable), pas un trou de sécurité.
//
// « Tableau de bord » reste la première entrée pour chaque rôle. Le menu
// « Rapports » mène à une synthèse détaillée, distincte de cette vue rapide.
//
// RÈGLE DE CONCEPTION — un MENU PLAT, PAS UN PLAN DE TABLE :
// ----------------------------------------------------------
// Une relation 1─N du modèle de données n'est PAS une raison de créer
// deux niveaux de menu : un sous-item n'a de valeur de navigation que si
// cet écran répond à une question posée INDÉPENDAMMENT de son parent
// (« situation mensuelle des loyers », « liste des impayés » : oui ;
// « les parcelles de quelle ville ? », « les baux de quel locataire ? » :
// non, c'est du drill-down DANS la page du parent). Le menu ci-dessous
// est donc entièrement PLAT : aucune entrée ne porte de `children`.
//
// Cascades de listes qui deviennent du drill-down (l'URL profonde reste
// partageable — le splat de route `patrimoine/*` la sert, et
// `isPathInMenu()` la déclare couverte par son entrée de premier niveau) :
//   - Patrimoine   découpe sa propre hiérarchie : Ville → Parcelle →
//                   Bâtiment → Unité, en fil d'Ariane interne.
//   - Locataires   héberge les Baux d'un locataire précis.
//   - Loyers       héberge les Paiements d'une échéance précise mais reste
//                   une entrée indépendante : le suivi mensuel des loyers
//                   et la liste des impayés se consultent SANS passer par
//                   un locataire précis.
//   - Personnel    héberge les Affectations d'un ouvrier précis (rôles
//                   ADMIN_IMMOBILIER uniquement depuis la fusion).
//   - Administration héberge l'Équipe et les Villes assignées d'un membre ;
//                   pour le PATRON, « Équipe » et « Personnel » désignent la
//                   même réalité (une seule liste, pas d'onglet).
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
    section: 'overview',
    path: '/app/dashboard',
};

/**
 * Patrimoine : UNE entrée, pas quatre. « Villes », « Parcelles »,
 * « Bâtiments » et « Unités » ne répondent à aucune question posée seule
 * (« les parcelles de QUELLE ville ? ») : ce sont quatre vues d'une même
 * chaîne hiérarchique, que la page Patrimoine parcourt en drill-down
 * (Ville → Parcelle → Bâtiment → Unité), pas quatre rapports indépendants.
 * Les URLs profondes (`/app/patrimoine/villes/:id/parcelles/:id`) restent
 * servies par le splat de route et couvertes par `isPathInMenu()`.
 */
const PATRIMOINE: AppMenuItem = {
    id: 'patrimoine',
    label: 'Patrimoine',
    icon: 'building',
    section: 'property',
    path: '/app/patrimoine',
};

/**
 * Locataires : entrée indépendante. Les Baux n'ont de sens qu'AVEC un
 * contexte (les baux de ce locataire) : ils ne figurent pas au menu, ils
 * sont atteints en drill-down depuis la page d'un locataire (et leur URL
 * profonde `/app/location/locataires/:id/baux` reste couverte).
 */
const LOCATAIRES: AppMenuItem = {
    id: 'location-locataires',
    label: 'Locataires',
    icon: 'user-single',
    section: 'property',
    path: '/app/location/locataires',
};

/**
 * Loyers : entrée indépendante, et c'est LE contre-exemple qui prouve la
 * règle. Le suivi financier des échéances se consulte SANS passer par un
 * locataire précis (« quelles échéances sont en retard ce mois-ci, toutes
 * propriétés confondues ? ») — exactement le « situation mensuelle des
 * loyers » et la « liste des impayés » du cahier des charges d'origine.
 * Les Paiements restent en drill-down depuis une échéance.
 */
const LOYERS: AppMenuItem = {
    id: 'location-loyers',
    label: 'Loyers',
    icon: 'calendar-due',
    section: 'finance',
    path: '/app/location/loyers',
};

/**
 * Dépenses : destination indépendante, avec une icône distincte de Loyers.
 *
 * Icône : `wallet`, volontairement distincte de celle de Loyers — les deux
 * sont des destinations financières de premier niveau, et deux icônes
 * proches (« pièces ») referaient la confusion qu'on cherche à lever.
 */
const DEPENSES: AppMenuItem = {
    id: 'depenses',
    label: 'Dépenses',
    icon: 'wallet',
    section: 'finance',
    path: '/app/depenses',
};

/** Vitrine publique : feuille sans sous-menu (vitrine/page publique à venir). */
const VITRINE: AppMenuItem = {
    id: 'vitrine',
    label: 'Vitrine',
    icon: 'storefront',
    section: 'operations',
    path: '/app/vitrine',
};

/**
 * Personnel : une entrée, exposée aux rôles qui n'ont PAS l'Administration
 * (ADMIN_IMMOBILIER). Les Affectations ne se consultent pas seules
 * (« les affectations de QUEL ouvrier ? ») : elles sont atteintes en
 * drill-down depuis la page d'un ouvrier, jamais au menu. Le PATRON, lui,
 * gère l'équipe — y compris les ouvriers — depuis sa page Administration.
 */
const PERSONNEL: AppMenuItem = {
    id: 'personnel',
    label: 'Personnel',
    icon: 'hard-hat',
    section: 'operations',
    path: '/app/personnel',
};

/**
 * Administration : une entrée. « Équipe » et « Villes assignées » se
 * gèrent depuis la fiche d'un membre de l'équipe (drill-down), pas au menu.
 * Pour le PATRON, l'Équipe regroupe les personnes (cadres et ouvriers) :
 * « Personnel » n'est donc pas une entrée de son menu.
 */
const ADMINISTRATION: AppMenuItem = {
    id: 'administration',
    label: 'Administration',
    icon: 'id-badge',
    section: 'administration',
    path: '/app/administration',
};

const RAPPORTS: AppMenuItem = {
    id: 'rapports',
    label: 'Rapports',
    icon: 'report',
    section: 'overview',
    path: '/app/rapports',
};


/**
 * Menu des rôles métier d'organisation, par rôle.
 *
 * - `patron` : tout le menu, avec « Administration » qui regroupe l'Équipe
 *   (une seule liste : cadres et ouvriers) — « Personnel » n'est donc plus
 *   une entrée à part pour ce rôle.
 * - `admin_immobilier` : opérations + Personnel, qui est la 7e entrée
 *   (le backend lui accorde CREATE/UPDATE/DELETE sur Worker, cf.
 *   `SecurityService::checkAdminImmobilierAction`). Seule l'Administration
 *   reste réservée au PATRON.
 * - `admin_ville` : opérations sans Personnel (`VIEW_WORKER` seul : une
 *   entrée dédiée laisserait croire à des droits d'écriture) et sans
 *   Administration.
 */
export const ORGANIZATION_SIDEBAR: Record<OrganizationRole, SidebarMenu> = {
    patron: [DASHBOARD, RAPPORTS, PATRIMOINE, LOCATAIRES, LOYERS, DEPENSES, VITRINE, ADMINISTRATION],
    admin_immobilier: [DASHBOARD, RAPPORTS, PATRIMOINE, LOCATAIRES, LOYERS, DEPENSES, VITRINE, PERSONNEL],
    admin_ville: [DASHBOARD, RAPPORTS, PATRIMOINE, LOCATAIRES, LOYERS, DEPENSES, VITRINE],
};

/** Menu SUPER_ADMIN : aucune entrée métier d'organisation. */
export const PLATFORM_SIDEBAR: SidebarMenu = [
    {
        id: 'plateforme-dashboard',
        label: 'Tableau de bord',
        icon: 'gauge',
        section: 'overview',
        path: '/app/admin/dashboard',
    },
    {
        id: 'plateforme-organisations',
        label: 'Organisations',
        icon: 'briefcase',
        section: 'platform',
        path: '/app/admin/organisations',
    },
    {
        id: 'plateforme-utilisateurs',
        label: 'Utilisateurs',
        icon: 'users',
        section: 'platform',
        path: '/app/admin/utilisateurs',
    },
    {
        id: 'plateforme-audit',
        label: 'Journal d\'audit',
        icon: 'audit',
        section: 'platform',
        path: '/app/admin/audit',
    },
    {
        id: 'plateforme-rapports',
        label: 'Rapports',
        icon: 'report',
        section: 'overview',
        path: '/app/admin/rapports',
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
 * Vrai si `pathname` est couvert par une entrée du menu.
 *
 * Le menu est plat : une entrée couvre sa propre page ET tous les
 * drill-down sous son chemin (`/app/patrimoine/villes/12/parcelles/4` est
 * couvert par `/app/patrimoine`) — les URLs profondes restent partageables
 * par lien même quand le sidebar n'affiche que l'entrée de premier niveau.
 *
 * La garde de route (`AppRouteGuard`) s'appuie exactement sur la même
 * vérité que le menu : une route non couverte par le menu du rôle courant
 * est hors de portée. Confort d'affichage uniquement — l'API reste
 * l'autorité de sécurité (une URL forgée sera de toute façon refusée en
 * 403 par le backend).
 */
export function isPathInMenu(menu: SidebarMenu, pathname: string): boolean {
    return menu.some(item => item.path !== undefined
        && (pathname === item.path || pathname.startsWith(`${item.path}/`)));
}

/**
 * Chemin d'atterrissage par défaut pour un rôle donné : toujours le
 * Tableau de bord quand il existe (première entrée de chaque menu), sinon
 * la première entrée portant un `path`.
 */
export function defaultPathFor(platformRole: PlatformRole | null, organizationRole: OrganizationRole | null): string {
    const menu = resolveSidebar(platformRole, organizationRole);

    for (const item of menu) {
        if (item.path) return item.path;
    }

    return '/app/access-denied';
}
