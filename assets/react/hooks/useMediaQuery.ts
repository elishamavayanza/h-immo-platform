import { useCallback, useSyncExternalStore } from 'react';
import { isClient, matchesQuery } from '../services/device';

/**
 * Abonnement réactif à une requête média via `useSyncExternalStore`.
 *
 * Contrairement à un écouteur `resize`, rempli via `matchMedia` + son
 * événement `change` : réagit à la vraie transition de point de rupture
 * et reste léger. Sans `window` (SSR) la valeur de secours est `false`.
 */
export function useMediaQuery(query: string): boolean {
    const subscribe = useCallback(
        (onStoreChange: () => void): (() => void) => {
            if (!isClient) return () => {};

            const mediaQueryList = window.matchMedia(query);
            mediaQueryList.addEventListener('change', onStoreChange);
            return () => mediaQueryList.removeEventListener('change', onStoreChange);
        },
        [query],
    );

    const getSnapshot = useCallback(() => matchesQuery(query), [query]);

    return useSyncExternalStore(subscribe, getSnapshot, () => false);
}