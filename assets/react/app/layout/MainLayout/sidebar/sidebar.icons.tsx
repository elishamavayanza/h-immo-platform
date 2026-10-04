// ============================================================
// upload/react/app/layout/MainLayout/sidebar/sidebar.icons.tsx
// Jeu d'icônes SVG minimal du menu.
//
// Traits `currentColor` : l'icône hérite de la couleur du texte du
// menu (`_Sidebar.scss`), active ou non, sans surcharge par icône.
// ============================================================

import type { ReactNode } from 'react';

const STROKE = {
    viewBox: '0 0 24 24',
    width: 20,
    height: 20,
    fill: 'none',
    stroke: 'currentColor',
    strokeWidth: 2,
    strokeLinecap: 'round',
    strokeLinejoin: 'round',
} as const;

/** Bâtiment — Patrimoine. */
export const IconBuilding = (): ReactNode => (
    <svg {...STROKE}>
        <path d="M3 21h18" />
        <path d="M5 21V5a1 1 0 0 1 1-1h9a1 1 0 0 1 1 1v16" />
        <path d="M16 9h3a1 1 0 0 1 1 1v11" />
        <path d="M9 8h2M9 12h2M9 16h2" />
    </svg>
);

/** Clé — Location. */
export const IconKey = (): ReactNode => (
    <svg {...STROKE}>
        <circle cx="8" cy="15" r="4" />
        <path d="M10.8 12.2 21 2" />
        <path d="M17 6l3 3" />
    </svg>
);

/** Devise — Finances. */
export const IconCoins = (): ReactNode => (
    <svg {...STROKE}>
        <circle cx="9" cy="9" r="6" />
        <path d="M14.5 5.5a6 6 0 1 1-9 9" />
        <path d="M9 6v6M6.5 8h5" />
    </svg>
);

/** Vitrine publique. */
export const IconStorefront = (): ReactNode => (
    <svg {...STROKE}>
        <path d="M4 10 5.5 4h13L20 10" />
        <path d="M4 10a2.5 2.5 0 0 0 5 0 2.5 2.5 0 0 0 5 0 2.5 2.5 0 0 0 5 0" />
        <path d="M5 13v7h14v-7" />
        <path d="M10 20v-4h4v4" />
    </svg>
);

/** Engrenage — Administration. */
export const IconGear = (): ReactNode => (
    <svg {...STROKE}>
        <circle cx="12" cy="12" r="3" />
        <path d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1 1.55V21a2 2 0 1 1-4 0v-.09a1.7 1.7 0 0 0-1-1.55 1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.55-1H3a2 2 0 1 1 0-4h.09a1.7 1.7 0 0 0 1.55-1 1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.7 1.7 0 0 0 1.87.34h0a1.7 1.7 0 0 0 1-1.55V3a2 2 0 1 1 4 0v.09a1.7 1.7 0 0 0 1 1.55h0a1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.7 1.7 0 0 0-.34 1.87v0a1.7 1.7 0 0 0 1.55 1H21a2 2 0 1 1 0 4h-.09a1.7 1.7 0 0 0-1.55 1Z" />
    </svg>
);

/** Casque — Personnel. */
export const IconHardHat = (): ReactNode => (
    <svg {...STROKE}>
        <path d="M3 18h18" />
        <path d="M8 18v-6a4 4 0 0 1 4-4 4 4 0 0 1 4 4v6" />
        <path d="M12 5a7 7 0 0 0-7 7v-1.5a1 1 0 0 0-2 0V12" />
        <path d="M19 12v-1.5a1 1 0 0 1 2 0V12" />
    </svg>
);

/** Utilisateurs — Équipe. */
export const IconUsers = (): ReactNode => (
    <svg {...STROKE}>
        <circle cx="9" cy="8" r="3.5" />
        <path d="M3.5 20a5.5 5.5 0 0 1 11 0" />
        <path d="M16 8a3 3 0 0 1 3 3" />
        <path d="M17 14.5a4.5 4.5 0 0 1 3.5 5.5" />
    </svg>
);

/** Organisation (carte grille) — plateau SUPER_ADMIN. */
export const IconOrganizations = (): ReactNode => (
    <svg {...STROKE}>
        <rect x="3" y="3" width="7" height="7" rx="1" />
        <rect x="14" y="3" width="7" height="7" rx="1" />
        <rect x="3" y="14" width="7" height="7" rx="1" />
        <rect x="14" y="14" width="7" height="7" rx="1" />
    </svg>
);

/** Journal d'audit. */
export const IconAudit = (): ReactNode => (
    <svg {...STROKE}>
        <path d="M6 10h12M6 6h12M6 14h12M6 18h7" />
        <path d="m15 18 2 2 3.5-4" />
    </svg>
);

/** Taux de change. */
export const IconExchange = (): ReactNode => (
    <svg {...STROKE}>
        <path d="M3 8h13l-3-3M21 16H8l3 3" />
        <path d="M16 5l5 3M3 16l5-3" />
    </svg>
);

/** Carte bâtiment — SUPER_ADMIN (alias). */
export const IconAdminBuilding = IconBuilding;

/** Signature d'un composant d'icône. */
export type IconComponent = () => ReactNode;

/**
 * Résout le NOM d'icône déclaré dans `sidebar.config.ts` (module pur,
 * sans JSX) vers le composant SVG correspondant au rendu.
 */
export const SIDEBAR_ICON_MAP: Record<string, IconComponent> = {
    building: IconBuilding,
    key: IconKey,
    coins: IconCoins,
    storefront: IconStorefront,
    gear: IconGear,
    'hard-hat': IconHardHat,
    users: IconUsers,
    organizations: IconOrganizations,
    audit: IconAudit,
    exchange: IconExchange,
};
