// ============================================================
// assets/react/hook-components/Navigation/Sidebar/sidebar.state.ts
// Règles pures d'affichage du menu latéral.
//
// Module PUR (ni React, ni JSX, aucun import de valeur) : ces règles
// sont donc exécutables directement par Node
// (`tests/verify-sidebar-roles.ts`), sans navigateur ni DOM.
//
// Elles sont isolées ici, et non laissées dans `useSidebar` et
// `Sidebar.tsx`, parce qu'elles portent les deux décisions qu'il est
// facile de casser en refactorisant : à quel écran le sous-menu peut
// être flottant, et quelle entrée doit rester allumée quand le menu
// est réduit à des icônes.
// ============================================================

import type { SidebarItem } from './types.ts';

export interface SidebarDisplayState {
    /** Sous 768px : le panneau est un rail, le logo ouvre un tiroir. */
    isMobile: boolean;
    /** Rail mobile : tiroir fermé. */
    isRail: boolean;
    /** Repli choisi par l'utilisateur sur desktop. */
    isCollapsed: boolean;
    /** Le menu peut-il être replié ? */
    collapsible: boolean;
}

/**
 * Libellés masqués : rail mobile, ou menu replié sur desktop.
 *
 * C'est l'inverse de l'état « les libellés sont lisibles », et non
 * l'inverse de « le tiroir est fermé » : sur mobile, tiroir fermé et
 * libellés masqués coïncident, mais sur desktop un tiroir n'existe
 * pas et `isMobileOpen` vaut toujours `false`.
 */
export function isLabelHidden(state: SidebarDisplayState): boolean {
    return state.isMobile ? state.isRail : state.collapsible && state.isCollapsed;
}

/**
 * Le flyout de sous-menu est-il utilisable ?
 *
 * Non, et volontairement, dès qu'un tiroir peut afficher les libellés :
 *
 *   - mobile, tiroir fermé : un clic sur un parent ouvre le tiroir
 *     (comportement antérieur, conservé) ;
 *   - desktop, menu déplié : les sous-menu sont déjà en ligne.
 *
 * Afficher un panneau flottant dans ces deux cas dupliquerait le même
 * contenu à deux endroits, dont un hors du `<aside>` où il flotterait
 * au-dessus de la page. Le flyout ne sert donc que le cas réel : un rail
 * desktop ou tablette, sans tiroir disponible.
 */
export function isFlyoutAvailable(state: SidebarDisplayState): boolean {
    return isLabelHidden(state) && !state.isMobile;
}

/**
 * Un parent de sous-menu doit-il porter l'état actif ?
 *
 * Vrai s'il est lui-même dans la route courante, ou si l'un de ses
 * descendants l'est. C'est indispensable en rail : le parent n'affiche
 * plus qu'une icône, et c'est elle qui doit indiquer dans quel groupe
 * se trouve la page affichée.
 *
 * La descente est récursive alors que la configuration ne porte qu'un
 * niveau : un sous-menu qui gagne sa propre section ne doit pas, ce
 * jour-là, être traité comme une feuille.
 */
export function isBranchActive(item: SidebarItem, activeIds: ReadonlySet<string>): boolean {
    if (activeIds.has(item.id)) return true;
    if (item.children === undefined || item.children.length === 0) return false;

    return item.children.some((child) => isBranchActive(child, activeIds));
}
