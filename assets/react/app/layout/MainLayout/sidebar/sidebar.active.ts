// ============================================================
// assets/react/app/layout/MainLayout/sidebar/sidebar.active.ts
// Résolution de la route active pour le menu latéral.
//
// Module PUR (ni React ni JSX, aucun import), comme
// `sidebar.config.ts` : la logique est donc exécutable par Node
// (`tests/verify-sidebar-roles.ts`), qui la confronte au menu
// réel. C'est ce qui permet de garantir qu'une route imbriquée
// active bien sa section, sans React ni navigateur.
//
// Deux besoins distincts, volontairement séparés :
//
//   1. `isRouteMatch(route, pathname)` — correspondance par
//      SEGMENT de chemin, pas par égalité de chaîne. `/app/dashboard`
//      doit rester actif sur `/app/dashboard` mais aussi sur une
//      route fille (`/app/dashboard/123`) ; à l'inverse `/app/location`
//      ne doit PAS activer `/app/location-paiements` (le segment
//      suivant est un `-`, pas un `/`).
//
//   2. `resolveActiveMenu(menu, pathname)` — ensemble des ids
//      actifs : la feuille ET toutes ses sections parentes. Sans
//      cette remontée, un utilisateur arrivant sur
//      `/app/patrimoine/villes` ne verrait AUCUN item en état actif
//      (le parent n'a pas de `path`), donc il ne saurait pas dans
//      quelle section il se trouve.
//
// Le retour est un `Set<string>` d'ids : l'appelant s'en sert pour
// marquer les items (`active`) et pour ouvrir d'emblée les sections
// concernées (`defaultOpen`). Aucune décision d'autorisation ici :
// le menu reste un confort d'affichage, l'API demeure l'autorité.
// ============================================================

import type { SidebarMenu } from './sidebar.types';

/**
 * Vrai si `pathname` correspond à `route`, routes filles comprises.
 *
 * La comparaison se fait sur les segments : on normalise le `/` final
 * puis on exige soit l'égalité, soit un préfixe suivi d'un `/`. Cette
 * borne évite les faux positifs par préfixe simple (`/app/location`
 * matcherait `/app/location-paiements`), qui afficheraient deux
 * sections actives à la fois.
 */
export function isRouteMatch(route: string | undefined, pathname: string): boolean {
    if (route === undefined || route === '') return false;
    if (route === pathname) return true;

    const base = route.endsWith('/') ? route.slice(0, -1) : route;

    if (pathname === base) return true;

    return pathname.startsWith(`${base}/`);
}

/**
 * Ids actifs du menu pour `pathname` : la feuille visée plus toutes
 * ses sections parentes (une seule profondeur de sous-menu dans la
 * configuration, mais la fonction reste récursive).
 *
 * Une route hors menu renvoie un ensemble vide : c'est le cas normal
 * d'un 404 ou d'une page sans entrée de menu, pas une erreur.
 */
export function resolveActiveMenu(menu: SidebarMenu, pathname: string): Set<string> {
    const active = new Set<string>();

    // Retourne `true` si cette branche contient la route active, ce qui
    // permet au parent d'ajouter son propre id sans re-parcourir le menu.
    const walk = (items: SidebarMenu): boolean => {
        let branchActive = false;

        for (const item of items) {
            const selfActive = isRouteMatch(item.path, pathname);
            const childActive = item.children !== undefined ? walk(item.children) : false;

            if (selfActive || childActive) {
                active.add(item.id);
                branchActive = true;
            }
        }

        return branchActive;
    };

    walk(menu);

    return active;
}
