import type { AccountSettings } from '../types/settings.types';

const STORAGE_KEY = 'h-immo-account-settings';
const DEFAULTS: AccountSettings = { compactTables: false, emailNotifications: true };

export const settingsService = {
    get(): AccountSettings {
        try {
            const saved: unknown = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? 'null');
            if (saved && typeof saved === 'object') return { ...DEFAULTS, ...(saved as Partial<AccountSettings>) };
        } catch { /* Un réglage illisible ne doit pas bloquer le compte. */ }
        return DEFAULTS;
    },
    save(settings: AccountSettings): void { localStorage.setItem(STORAGE_KEY, JSON.stringify(settings)); },
};
