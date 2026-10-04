import { MEDIA_QUERIES } from '../services/device';
import { useMediaQuery } from './useMediaQuery';

/** True sur écran de bureau : largeur >= 1024 px. */
export function useIsDesktop(): boolean {
    return useMediaQuery(MEDIA_QUERIES.deviceDesktop);
}