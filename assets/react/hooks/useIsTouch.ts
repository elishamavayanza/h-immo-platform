import { MEDIA_QUERIES } from '../services/device';
import { useMediaQuery } from './useMediaQuery';

/**
 * True si le pointeur principal est « grossier » (doigt) : cibles
 * tactiles plus grandes, pas d'interaction hover fiable.
 */
export function useIsTouch(): boolean {
    return useMediaQuery(MEDIA_QUERIES.touch);
}