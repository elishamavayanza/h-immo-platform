import { useMemo } from 'react';
import { DeviceType, MEDIA_QUERIES } from '../services/device';
import { useMediaQuery } from './useMediaQuery';

export interface DeviceDescriptor {
    deviceType: DeviceType;
    isMobile: boolean;
    isTablet: boolean;
    isDesktop: boolean;
    isCompact: boolean;
    isPortrait: boolean;
    isTouch: boolean;
}

/**
 * Descripteur d'appareil complet, réactif.
 *
 * Les six signaux proviennent de requêtes média partagées
 * (`MEDIA_QUERIES`) : un seul point de définition par famille, donc
 * aucune duplication d'écouteurs ni de seuils entre composants.
 */
export function useDevice(): DeviceDescriptor {
    const isMobile = useMediaQuery(MEDIA_QUERIES.deviceMobile);
    const isTablet = useMediaQuery(MEDIA_QUERIES.deviceTablet);
    const isDesktop = useMediaQuery(MEDIA_QUERIES.deviceDesktop);
    const isPortrait = useMediaQuery(MEDIA_QUERIES.portrait);
    const isTouch = useMediaQuery(MEDIA_QUERIES.touch);

    return useMemo<DeviceDescriptor>(
        () => {
            const deviceType: DeviceType = isMobile ? 'mobile' : isTablet ? 'tablet' : 'desktop';

            return {
                deviceType,
                isMobile,
                isTablet,
                isDesktop,
                isCompact: isMobile || isTablet,
                isPortrait,
                isTouch,
            };
        },
        [isMobile, isTablet, isDesktop, isPortrait, isTouch],
    );
}