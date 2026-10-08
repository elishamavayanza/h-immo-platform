import { useCallback, useEffect, useRef, useState } from 'react';

import { useToast } from '../../../../../app/layout/MainLayout/contexts/ToastContext';
import { ApiError } from '../../../../../../services/api/api.types';
import { settingsService } from '../services/settingsService';
import type { UserSettings } from '../types/settings.types';
import { saveUserPreferences } from '../../../../../services/userPreferences';

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
    const settingsRef = useRef(settings);
    const revisions = useRef(new Map<keyof UserSettings, number>());

    const commitSettings = useCallback((next: UserSettings) => {
        settingsRef.current = next;
        setSettings(next);
        saveUserPreferences(next);
    }, []);

    const load = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const settings = await settingsService.getMySettings();
            const merged = { ...settingsRef.current, ...settings };
            commitSettings(merged);
        } catch (cause) {
            setError(cause instanceof ApiError ? cause.message : 'Impossible de charger les préférences');
        } finally {
            setIsLoading(false);
        }
    }, [commitSettings]);

    useEffect(() => { void load(); }, [load]);

    const update = useCallback(async (key: keyof UserSettings, value: unknown) => {
        const previousValue = settingsRef.current[key];
        const revision = (revisions.current.get(key) ?? 0) + 1;
        revisions.current.set(key, revision);
        commitSettings({ ...settingsRef.current, [key]: value });
        try {
            const updated = await settingsService.updateMySettings({ [key]: value });
            if (revisions.current.get(key) === revision) {
                commitSettings({ ...settingsRef.current, [key]: updated[key] });
            }
            push('success', 'Préférence mise à jour');
            return true;
        } catch (cause) {
            if (revisions.current.get(key) === revision) {
                commitSettings({ ...settingsRef.current, [key]: previousValue });
            }
            push('error', cause instanceof ApiError ? cause.message : 'Échec de la mise à jour');
            return false;
        }
    }, [commitSettings, push]);

    return { settings, update, isLoading, error, reload: load };
}
