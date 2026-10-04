import { MEDIA_QUERIES } from '../services/device';
import { useMediaQuery } from './useMediaQuery';

/** True en orientation portrait (hauteur supérieure à la largeur). */
export function useIsPortrait(): boolean {
    return useMediaQuery(MEDIA_QUERIES.portrait);
}