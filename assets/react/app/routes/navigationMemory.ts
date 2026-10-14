import type { AppRoute } from './appRoutes.config';

const STORAGE_PREFIX = 'soft-immo:last-location:v1:';

/** Mémorise uniquement une destination interne de l'espace connecté. */
export function rememberLastLocation(userUuid: string, location: { pathname: string; search: string; hash: string }): void {
    if (!location.pathname.startsWith('/app/') && !['/profile', '/settings', '/settings/organisation'].includes(location.pathname)) return;
    if (location.pathname === '/app/access-denied') return;

    try {
        window.localStorage.setItem(`${STORAGE_PREFIX}${userUuid}`, JSON.stringify(location));
    } catch {
        // La navigation reste fonctionnelle si le stockage du navigateur est désactivé.
    }
}

/** Renvoie la dernière destination seulement si elle correspond à une route connue. */
export function readLastLocation(userUuid: string, routes: readonly AppRoute[]): string | null {
    try {
        const raw = window.localStorage.getItem(`${STORAGE_PREFIX}${userUuid}`);
        if (!raw) return null;
        const saved = JSON.parse(raw) as Partial<{ pathname: string; search: string; hash: string }>;
        if (typeof saved.pathname !== 'string' || typeof saved.search !== 'string' || typeof saved.hash !== 'string') return null;
        const pathname = saved.pathname;

        const isKnownRoute = routes.some(({ path }) => pathname === path || pathname.startsWith(`${path}/`));
        const isAccountRoute = ['/profile', '/settings', '/settings/organisation'].includes(pathname);
        if (!isKnownRoute && !isAccountRoute) return null;
        if (pathname === '/app/access-denied') return null;

        return `${pathname}${saved.search}${saved.hash}`;
    } catch {
        return null;
    }
}
