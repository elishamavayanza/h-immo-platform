import { useCallback, useEffect, useState } from 'react';

import { useToast } from '../../../../../app/layout/MainLayout/contexts/ToastContext';
import { ApiError } from '../../../../../../services/api/api.types';
import { settingsService } from '../services/settingsService';
import type { UserSettings } from '../types/settings.types';

export function useAccountSettings() {
    const { push } = useToast();
    const [settings, setSettings] = useState<UserSettings>({
        compactTables: false,
        emailNotifications: true,
        locale: 'fr',
        theme: 'system',
        dateFormat: 'DD/MM/YYYY',
        inAppNotifications: true,
        reducedMotion: false,
    });
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const load = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const settings = await settingsService.getMySettings();
            setSettings(settings);
        } catch (cause) {
            setError(cause instanceof ApiError ? cause.message : 'Impossible de charger les préférences');
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => { void load(); }, [load]);

    const update = useCallback(async (key: keyof UserSettings, value: unknown) => {
        const next = { ...settings, [key]: value };
        setSettings(next);
        try {
            await settingsService.updateMySettings({ [key]: value });
            push('success', 'Préférence mise à jour');
        } catch (cause) {
            setSettings(settings);
            push('error', cause instanceof ApiError ? cause.message : 'Échec de la mise à jour');
        }
    }, [settings, push]);

    return { settings, update, isLoading, error, reload: load };
}