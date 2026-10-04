// ============================================================
// DÉTECTION D'APPAREIL — SOURCE UNIQUE DE VÉRITÉ
// ------------------------------------------------------------
// Les seuils sont alignés sur le design system SCSS
// (`assets/styles/baseVariables/_variables.scss`, breakpoints
// mobile-first) pour que le TypeScript et le CSS ne puissent pas
// diverger :
//     $breakpoint-mobile :  480px
//     $breakpoint-tablet :  768px
//     $breakpoint-desktop: 1024px
//     $breakpoint-large  : 1440px
//     $breakpoint-xlarge : 1920px
//
// Classification en trois familles (seuils tablette / desktop) :
//     mobile : width  < 768   → layout off-canvas / drawer
//     tablet : 768 <= width < 1024
//     desktop: width >= 1024
//
// « Compact » (nav repliée, panneaux en drawer) = mobile + tablette,
// soit width < 1024.
//
// Les primitives utilisent `window.matchMedia` (jamais d'événement
// `resize` global) et sont sûres pour le SSR : un environnement sans
// `window` renvoie des valeurs par défaut sans se tromper.
// ============================================================

export const BREAKPOINTS = {
    mobile: 480,
    tablet: 768,
    desktop: 1024,
    large: 1440,
    xlarge: 1920,
} as const;

export type DeviceType = 'mobile' | 'tablet' | 'desktop';
export type Orientation = 'portrait' | 'landscape';

/**
 * Requêtes média partagées entre le service et les hooks.
 * Nommées par « famille d'appareil » pour rester lisibles,
 * les seuils étant dérivés d'un unique objet `BREAKPOINTS`.
 */
export const MEDIA_QUERIES = {
    deviceMobile: `(max-width: ${BREAKPOINTS.tablet - 1}px)`,
    deviceTablet: `(min-width: ${BREAKPOINTS.tablet}px) and (max-width: ${BREAKPOINTS.desktop - 1}px)`,
    deviceDesktop: `(min-width: ${BREAKPOINTS.desktop}px)`,
    compact: `(max-width: ${BREAKPOINTS.desktop - 1}px)`,
    portrait: '(orientation: portrait)',
    landscape: '(orientation: landscape)',
    touch: '(pointer: coarse)',
    finePointer: '(pointer: fine)',
    reducedMotion: '(prefers-reduced-motion: reduce)',
} as const;

export const isClient = typeof window !== 'undefined';

/** Évalue une requête média ; `false` hors navigateur (SSR). */
export function matchesQuery(query: string): boolean {
    if (!isClient) return false;
    return window.matchMedia(query).matches;
}

// ------------------------------------------------------------------
// Prédicats impératifs (usage hors React : helpers, calculs synchro).
// ------------------------------------------------------------------

export function isMobileWidth(): boolean {
    return matchesQuery(MEDIA_QUERIES.deviceMobile);
}

export function isTabletWidth(): boolean {
    return matchesQuery(MEDIA_QUERIES.deviceTablet);
}

export function isDesktopWidth(): boolean {
    return matchesQuery(MEDIA_QUERIES.deviceDesktop);
}

export function isCompactWidth(): boolean {
    return matchesQuery(MEDIA_QUERIES.compact);
}

export function isPortraitDevice(): boolean {
    return matchesQuery(MEDIA_QUERIES.portrait);
}

export function isLandscapeDevice(): boolean {
    return matchesQuery(MEDIA_QUERIES.landscape);
}

export function isTouchDevice(): boolean {
    return matchesQuery(MEDIA_QUERIES.touch);
}

export function prefersReducedMotion(): boolean {
    return matchesQuery(MEDIA_QUERIES.reducedMotion);
}

export function getDeviceType(): DeviceType {
    if (isMobileWidth()) return 'mobile';
    if (isTabletWidth()) return 'tablet';
    return 'desktop';
}

export function getOrientation(): Orientation {
    return isPortraitDevice() ? 'portrait' : 'landscape';
}

// ------------------------------------------------------------------
// Descripteur complet (instantané synchrone).
// ------------------------------------------------------------------

export interface DeviceDescriptor {
    deviceType: DeviceType;
    orientation: Orientation;
    isMobile: boolean;
    isTablet: boolean;
    isDesktop: boolean;
    isCompact: boolean;
    isPortrait: boolean;
    isLandscape: boolean;
    isTouch: boolean;
    prefersReducedMotion: boolean;
}

export function getDeviceDescriptor(): DeviceDescriptor {
    return {
        deviceType: getDeviceType(),
        orientation: getOrientation(),
        isMobile: isMobileWidth(),
        isTablet: isTabletWidth(),
        isDesktop: isDesktopWidth(),
        isCompact: isCompactWidth(),
        isPortrait: isPortraitDevice(),
        isLandscape: isLandscapeDevice(),
        isTouch: isTouchDevice(),
        prefersReducedMotion: prefersReducedMotion(),
    };
}