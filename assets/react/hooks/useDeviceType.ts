import { DeviceType, MEDIA_QUERIES } from '../services/device';
import { useMediaQuery } from './useMediaQuery';

/** Famille d'appareil : 'mobile' | 'tablet' | 'desktop'. */
export function useDeviceType(): DeviceType {
    const isMobile = useMediaQuery(MEDIA_QUERIES.deviceMobile);
    const isTablet = useMediaQuery(MEDIA_QUERIES.deviceTablet);

    if (isMobile) return 'mobile';
    if (isTablet) return 'tablet';
    return 'desktop';
}