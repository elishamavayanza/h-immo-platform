import type { UserSettings } from '../features/shared/account/settings/types/settings.types';

const STORAGE_KEY = 'h-immo:user-settings';
const PENDING_LOGIN_THEME_KEY = 'h-immo:pending-login-theme';

export type ThemePreference = 'system' | 'light' | 'dark';
export type AccentPalette = 'ocean' | 'violet' | 'sage' | 'amber' | 'coral' | 'slate';

/** Enregistre et applique un choix de thème local avant l'authentification. */
export function setThemePreference(theme: Exclude<ThemePreference, 'system'>): void {
    if (typeof window === 'undefined') return;

    const settings = readUserPreferences();
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify({ ...settings, theme }));
    window.localStorage.setItem(PENDING_LOGIN_THEME_KEY, theme);
    applyTheme(theme);
}

export function readPendingLoginTheme(): Exclude<ThemePreference, 'system'> | null {
    if (typeof window === 'undefined') return null;
    const theme = window.localStorage.getItem(PENDING_LOGIN_THEME_KEY);
    return theme === 'light' || theme === 'dark' ? theme : null;
}

export function clearPendingLoginTheme(): void {
    if (typeof window !== 'undefined') window.localStorage.removeItem(PENDING_LOGIN_THEME_KEY);
}

export function resolveAccentPalette(value: unknown): AccentPalette {
    return value === 'violet' || value === 'sage' || value === 'amber' || value === 'coral' || value === 'slate'
        ? value
        : 'ocean';
}

export function resolveTheme(preference: ThemePreference, systemPrefersLight: boolean): 'light' | 'dark' {
    if (preference === 'light' || preference === 'dark') return preference;
    return systemPrefersLight ? 'light' : 'dark';
}

function applyTheme(preference: unknown): void {
    if (typeof document === 'undefined') return;
    const validPreference: ThemePreference = preference === 'light' || preference === 'dark' ? preference : 'system';
    const media = window.matchMedia('(prefers-color-scheme: light)');
    document.documentElement.dataset.theme = resolveTheme(validPreference, media.matches);
    document.documentElement.style.colorScheme = resolveTheme(validPreference, media.matches);
}

function applyAccentPalette(palette: unknown): void {
    if (typeof document === 'undefined') return;
    document.documentElement.dataset.accent = resolveAccentPalette(palette);
}

/** Applique les préférences locales avant le premier rendu React pour éviter un flash de thème. */
export function initializeUserPreferences(): void {
    const settings = readUserPreferences();
    applyTheme(settings.theme);
    applyAccentPalette(settings.accentPalette);
    if (typeof document !== 'undefined') {
        if (typeof settings.compactTables === 'boolean') document.documentElement.dataset.compactTables = String(settings.compactTables);
        if (typeof settings.reducedMotion === 'boolean') document.documentElement.dataset.reducedMotion = String(settings.reducedMotion);
    }
}

if (typeof window !== 'undefined') {
    window.matchMedia('(prefers-color-scheme: light)').addEventListener('change', () => {
        const preference = readUserPreferences().theme;
        if (preference === 'system' || !preference) applyTheme('system');
    });
}

export function saveUserPreferences(settings: UserSettings): void {
    if (typeof window === 'undefined') return;
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(settings));
    document.documentElement.dataset.compactTables = String(settings.compactTables);
    document.documentElement.dataset.reducedMotion = String(settings.reducedMotion);
    applyTheme(settings.theme);
    applyAccentPalette(settings.accentPalette);
}

export function readUserPreferences(): Partial<UserSettings> {
    if (typeof window === 'undefined') return {};
    try {
        return JSON.parse(window.localStorage.getItem(STORAGE_KEY) ?? '{}') as Partial<UserSettings>;
    } catch {
        return {};
    }
}

export function readDefaultPageSize(fallback = 12): number {
    const value = readUserPreferences().defaultPageSize;
    return typeof value === 'number' && Number.isInteger(value) && value >= 10 && value <= 100
        ? value
        : fallback;
}

export function readAuditLogDisplayDays(fallback = 30): number {
    const value = readUserPreferences().auditLogRetentionDays;
    return typeof value === 'number' && Number.isInteger(value) && value >= 30 && value <= 2555
        ? value
        : fallback;
}

export function areInAppNotificationsEnabled(): boolean {
    return readUserPreferences().inAppNotifications !== false;
}

export function formatUserDate(value: string | Date | null | undefined, withTime = false): string {
    if (!value) return '';
    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) return '';

    const format = readUserPreferences().dateFormat ?? 'DD/MM/YYYY';
    const parts: Record<string, string> = {
        DD: String(date.getDate()).padStart(2, '0'),
        MM: String(date.getMonth() + 1).padStart(2, '0'),
        YYYY: String(date.getFullYear()),
    };
    const datePart = format.replace(/YYYY|MM|DD/g, (token) => parts[token]);
    return withTime
        ? `${datePart} ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
        : datePart;
}
