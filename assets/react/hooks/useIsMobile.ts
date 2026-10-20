import { MEDIA_QUERIES } from '../services/device';
import { useMediaQuery } from './useMediaQuery';

/**
 * Vrai quand la largeur d'écran impose le tiroir de navigation mobile.
 * Au-dessus de 600 px, les tablettes conservent un rail visible, replié par défaut en portrait.
 */
export function useIsMobile(): boolean {
    return useMediaQuery(MEDIA_QUERIES.drawer);
}
