import { MEDIA_QUERIES } from '../services/device';
import { useMediaQuery } from './useMediaQuery';

/**
 * True sur écran « mobile » : largeur < 768 px (seuil tablette du
 * design system SCSS). Le layout passe alors en off-canvas / drawer.
 */
export function useIsMobile(): boolean {
    return useMediaQuery(MEDIA_QUERIES.deviceMobile);
}