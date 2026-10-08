import type { UserSettings } from '../features/shared/account/settings/types/settings.types';

const STORAGE_KEY = 'h-immo:user-settings';

export function saveUserPreferences(settings: UserSettings): void {
    if (typeof window === 'undefined') return;
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(settings));
    document.documentElement.dataset.compactTables = String(settings.compactTables);
    document.documentElement.dataset.reducedMotion = String(settings.reducedMotion);
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
