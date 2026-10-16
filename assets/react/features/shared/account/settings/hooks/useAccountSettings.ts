import { useCallback, useEffect, useRef, useState } from 'react';

import { useToast } from '../../../../../app/layout/MainLayout/contexts/ToastContext';
import { ApiError } from '../../../../../../services/api/api.types';
import { settingsService } from '../services/settingsService';
import type { UserSettings } from '../types/settings.types';
import { saveUserPreferences } from '../../../../../services/userPreferences';

const SETTING_LABELS: Record<keyof UserSettings, string> = {
    compactTables: 'l’affichage des tableaux compacts',
    emailNotifications: 'les notifications par e-mail',
    locale: 'la langue de l’interface',
    theme: 'le thème de l’interface',
    accentPalette: 'la couleur d’accent',
    dateFormat: 'le format des dates',
    inAppNotifications: 'les messages toast',
    reducedMotion: 'la réduction des animations',
    sidebarCollapsed: 'l’état initial du menu latéral',
    auditLogRetentionDays: 'la période d’affichage du journal d’audit',
    defaultPageSize: 'le nombre de lignes par page',
};

function preferenceSuccessMessage(key: keyof UserSettings, value: unknown): string {
    if (key === 'compactTables') return `Affichage compact des tableaux ${value ? 'activé' : 'désactivé'}.`;
    if (key === 'emailNotifications') return `Notifications par e-mail ${value ? 'activées' : 'désactivées'}.`;
    if (key === 'inAppNotifications') return `Messages toast ${value ? 'activés' : 'désactivés'}.`;
    if (key === 'reducedMotion') return `Animations réduites ${value ? 'activées' : 'désactivées'}.`;
    if (key === 'sidebarCollapsed') return `Menu latéral ${value ? 'replié' : 'déployé'} par défaut.`;
    if (key === 'defaultPageSize') return `Les tableaux afficheront ${value} lignes par page.`;
    if (key === 'theme') return `Thème de l’interface réglé sur ${value === 'system' ? 'le mode système' : value === 'light' ? 'le mode clair' : 'le mode sombre'}.`;
    if (key === 'dateFormat') return `Format des dates réglé sur ${value}.`;
    if (key === 'locale') return `Langue de l’interface réglée sur ${value}.`;
    if (key === 'accentPalette') return `Couleur d’accent réglée sur ${value}.`;
    if (key === 'auditLogRetentionDays') return `Période d’affichage du journal réglée sur ${value} jours.`;
    return `Préférence enregistrée : ${SETTING_LABELS[key]}.`;
}

export function useAccountSettings() {
    const { push } = useToast();
    const [settings, setSettings] = useState<UserSettings>({
        compactTables: false,
        emailNotifications: true,
        locale: 'fr',
        theme: 'system',
        accentPalette: 'ocean',
        dateFormat: 'DD/MM/YYYY',
        inAppNotifications: true,
        reducedMotion: false,
        sidebarCollapsed: false,
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
        if (Object.is(previousValue, value)) return true;
        const revision = (revisions.current.get(key) ?? 0) + 1;
        revisions.current.set(key, revision);
        commitSettings({ ...settingsRef.current, [key]: value });
        try {
            const updated = await settingsService.updateMySettings({ [key]: value });
            if (revisions.current.get(key) === revision) {
                commitSettings({ ...settingsRef.current, [key]: updated[key] });
            }
            push('success', preferenceSuccessMessage(key, value));
            return true;
        } catch (cause) {
            if (revisions.current.get(key) === revision) {
                commitSettings({ ...settingsRef.current, [key]: previousValue });
            }
            push('error', cause instanceof ApiError
                ? `Impossible d’enregistrer ${SETTING_LABELS[key]} : ${cause.message}`
                : `Impossible d’enregistrer ${SETTING_LABELS[key]}. Une erreur inattendue est survenue.`);
            return false;
        }
    }, [commitSettings, push]);

    return { settings, update, isLoading, error, reload: load };
}
