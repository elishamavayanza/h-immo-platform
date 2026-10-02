// ============================================================
// assets/react/app/routes/AppRoutes.tsx
// Table de routage du SPA.
//
// ⚠️ AUCUN ROUTEUR N'EST INSTALLÉ.
//
// `react-router-dom` n'est pas une dépendance du projet, et `AGENTS.md`
// interdit d'ajouter une dépendance React sans justification explicite.
// `main.tsx` fonctionne donc par simple lecture de `window.location.pathname`
// (Vite sert `index.html` pour toute route inconnue grâce à
// `appType: 'spa'`), ce qui suffit à l'unique page existante
// (`/reset-password`).
//
// Ce module centralise la table des routes connues pour que le
// branchement reste explicite et vérifiable. Il ne rend volontairement
// aucun composant : les écrans de la vitrine publique et du
// back-office ne sont pas encore écrits. Brancher une page se fait en
// ajoutant son chemin ici PUIS une entrée dans le `switch` de `main.tsx`.
//
// Si le nombre de pages croît au point où ce `switch` devient
// impossible à maintenir, c'est le moment de discuter l'ajout d'un
// routeur — pas avant.
// ============================================================

/** Chemins gérés par le SPA. */
export const ROUTES = {
    resetPassword: '/reset-password',
    showcase: '/showcase',
    login: '/login',
    dashboard: '/dashboard',
} as const;

export type RouteKey = keyof typeof ROUTES;

export interface RouteMatch {
    key: RouteKey;
    path: string;
    /**
     * Paramètre de chemin, le cas échéant. La vitrine publique est
     * `/showcase/{slug}` : le slug identifie l'Organization dont on
     * affiche le parc, et c'est le seul segment dynamique pour l'instant.
     */
    params: Record<string, string>;
}

/** Liste des routes à motif, dans l'ordre de tests. */
const PATTERNS: ReadonlyArray<{ key: RouteKey; regex: RegExp; params: string[] }> = [
    { key: 'showcase', regex: /^\/showcase\/([^/]+)\/?$/, params: ['slug'] },
    { key: 'resetPassword', regex: /^\/reset-password\/?$/, params: [] },
    { key: 'login', regex: /^\/login\/?$/, params: [] },
    { key: 'dashboard', regex: /^\/dashboard\/?$/, params: [] },
];

/**
 * Résout un chemin en route connue.
 * Retourne `null` si aucune route ne correspond : l'appelant affiche
 * alors son écran « introuvable » plutôt que de faire semblant.
 */
export function resolveRoute(pathname: string): RouteMatch | null {
    for (const pattern of PATTERNS) {
        const match = pattern.regex.exec(pathname);
        if (!match) continue;

        const params: Record<string, string> = {};
        pattern.params.forEach((name, index) => {
            params[name] = decodeURIComponent(match[index + 1]);
        });

        return { key: pattern.key, path: ROUTES[pattern.key], params };
    }

    return null;
}
