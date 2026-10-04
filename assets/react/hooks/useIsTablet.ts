import { MEDIA_QUERIES } from '../services/device';
import { useMediaQuery } from './useMediaQuery';

/** True sur tablette : 768 <= largeur < 1024 px. */
export function useIsTablet(): boolean {
    return useMediaQuery(MEDIA_QUERIES.deviceTablet);
}