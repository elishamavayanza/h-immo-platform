// ============================================================
// assets/react/app/routes/appRoutes.config.ts
// Table des routes du back-office, DÉDUITE du menu du sidebar.
// ============================================================
//
// Pourquoi ce module existe
// -----------------------
// Le menu (`sidebar.config.ts`) et le routeur doivent connaître EXACTEMENT
// les mêmes chemins : c'est le commentaire de `AppRoutes.tsx` qui l'exige.
// Écrire les routes à la main avait dérapé — aucune feuille du menu
// n'était enregistrée, si bien que :
//
//   /app  → AppLanding → /app/dashboard → aucun <Route> ne correspond
//         → `path="*"` → /app → /app/dashboard → …
//
// soit une BOUCLE DE REDIRECTION, et un menu entièrement mort.
//
// On dérive donc la table du menu : un chemin ajouté au menu devient
// automatiquement routable, et `tests/verify-sidebar-roles.ts` vérifie
// l'invariant (aucun lien mort, aucune destination d'atterrissage absente).
//
// Module PUR (ni React ni JSX) : exécutable par Node pour les tests.
//
// ⚠️ Ces routes ne sont PAS une sécurité : `AppRouteGuard` n'est qu'un
// confort d'UX. L'API reste l'autorité (cf. `SecurityService` côté backend).
// ============================================================

// Extension `.ts` explicite sur les imports de VALEUR : ce module est
// exécuté tel quel par Node (`node --experimental-strip-types`), qui ne
// résout pas les chemins sans extension.
import {
    defaultPathFor,
    resolveSidebar,
} from '../layout/MainLayout/sidebar/sidebar.config.ts';
import type { SidebarMenu } from '../layout/MainLayout/sidebar/sidebar.types.ts';
import type { OrganizationRole, PlatformRole } from '../../../services/api/api.types.ts';

/** Une feuille routable du back-office. */
export interface AppRoute {
    /** Chemin ABSOLU, tel qu'écrit dans le menu (`/app/patrimoine/villes`). */
    path: string;
    /** Identifiant de l'item de menu d'origine. */
    id: string;
    /** Libellé affiché par la page provisoire. */
    label: string;
}

/** Racine des routes du back-office : le préfixe des chemins du menu. */
export const APP_ROOT = '/app';

/** Chemin de la page « accès refusé », cible de `defaultPathFor` sans rôle. */
export const ACCESS_DENIED_PATH = '/app/access-denied';

/**
 * Toutes les combinaisons de rôles possibles.
 *
 * La table doit contenir l'UNION des feuilles de tous les rôles : le
 * routeur-matche l'URL avant que la garde ne statue sur le rôle. Si seule
 * la feuille du rôle courant était enregistrée, un `ADMIN_VILLE` qui saisit
 * `/app/personnel/ouvriers` tomberait sur le `*` — c'est-à-dire sur une
 * redirection, pas sur un 403 explicite.
 */
const ROLE_COMBINATIONS: ReadonlyArray<[PlatformRole | null, OrganizationRole | null]> = [
    ['super_admin', null],
    [null, 'patron'],
    [null, 'admin_immobilier'],
    [null, 'admin_ville'],
    [null, null],
];

/** Parcourt un menu et renvoie ses feuilles (items porteurs d'un `path`). */
function collectLeaves(menu: SidebarMenu, collected: AppRoute[]): void {
    for (const item of menu) {
        if (item.path !== undefined) {
            collected.push({ path: item.path, id: item.id, label: item.label });
        }

        if (item.children !== undefined) collectLeaves(item.children, collected);
    }
}

/**
 * Table des routes du back-office, sans doublon et triée (tri alphabétique :
 * la table ne dépend pas de l'ordre des rôles, donc le diff reste lisible).
 */
export function buildAppRoutes(): AppRoute[] {
    const byPath = new Map<string, AppRoute>();

    for (const [platformRole, organizationRole] of ROLE_COMBINATIONS) {
        const leaves: AppRoute[] = [];
        collectLeaves(resolveSidebar(platformRole, organizationRole), leaves);

        for (const leaf of leaves) {
            // Première venue gagne : le libellé est celui du rôle le plus
            // restrictif qui expose la feuille, ce qui évite d'afficher
            // « Dashboard » à un SUPER_ADMIN dont le menu dit
            // « Administration de la plateforme ».
            if (!byPath.has(leaf.path)) byPath.set(leaf.path, leaf);
        }
    }

    return [...byPath.values()].sort((a, b) => a.path.localeCompare(b.path));
}

/** Feuille routable correspondant à un chemin, ou `undefined`. */
export function findAppRoute(pathname: string): AppRoute | undefined {
    return buildAppRoutes().find(route => route.path === pathname);
}

/**
 * Chemin RELATIF attendu par un `<Route>` enfant de `/app`.
 * `/app/patrimoine/villes` → `patrimoine/villes`.
 */
export function toRelativeAppPath(path: string): string {
    return path.startsWith(`${APP_ROOT}/`) ? path.slice(APP_ROOT.length + 1) : path;
}

/**
 * Idem, mais avec un `splat` : `/app/patrimoine/villes` → `patrimoine/villes/*`.
 *
 * Une feuille du menu peut être pointée par une URL plus profonde que
 * lui-même (`/app/patrimoine/villes/42`), ce que la mise en évidence du
 * sidebar considère déjà comme « dans la page ». Avec le splat, ces liens
 * profonds affichent la feuille au lieu de retomber sur l'atterrissage du
 * rôle. En React Router v6+, `x/*` matche `x` comme `x/…`.
 */
export function toRelativeAppRoutePath(path: string): string {
    return `${toRelativeAppPath(path)}/*`;
}

/**
 * Destinations d'atterrissage : une par combinaison de rôles.
 *
 * C'est l'invariant qui interdit la boucle : `/app` redirige vers l'une de
 * ces destinations, donc chaque destination doit exister dans la table.
 */
export function buildLandingPaths(): string[] {
    return ROLE_COMBINATIONS.map(([platformRole, organizationRole]) =>
        defaultPathFor(platformRole, organizationRole)
    );
}
