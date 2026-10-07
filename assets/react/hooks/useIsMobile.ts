import { MEDIA_QUERIES } from '../services/device';
import { useMediaQuery } from './useMediaQuery';

/**
 * Vrai quand la largeur d'écran impose le tiroir mobile du sidebar.
 * L'orientation portrait seule ne déclenche pas le mode mobile : les
 * tablettes portrait gardent un sidebar de bureau replié en rail.
 */
export function useIsMobile(): boolean {
    return useMediaQuery(MEDIA_QUERIES.drawer);
}
