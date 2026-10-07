import { useState } from 'react';
import { settingsService } from '../services/settingsService';
import type { AccountSettings } from '../types/settings.types';

export function useAccountSettings() {
    const [settings, setSettings] = useState(settingsService.get);
    const update = (key: keyof AccountSettings, value: boolean) => setSettings((current) => {
        const next = { ...current, [key]: value };
        settingsService.save(next);
        return next;
    });
    return { settings, update };
}
