import { useCallback, useEffect, useState } from 'react';

import { useToast } from '../../../../app/layout/MainLayout/contexts/ToastContext';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { ApiError } from '../../../../../services/api/api.types';
import { actionErrorMessage, fieldErrorMap, isValidationError } from '../../../shared/utils/actionErrors';
import { changedFields } from '../../../shared/utils/changedFields';
import {
    deleteOrganizationLogo,
    fetchPublicSettings,
    updateShowcase,
    uploadOrganizationLogo,
} from '../services/publicSettingsService';
import type { OrganizationPublicSettings, ShowcasePayload } from '../types/publicSettings.types';

/**
 * Réglages publics de la vitrine du PATRON.
 *
 * Charge les paramètres de l'organization active, puis expose deux actions
 * d'écriture : la mise à jour des champs publics (slug/description/visibilité)
 * et la gestion du logo. Les erreurs 422 (slug invalide ou déjà pris, champ
 * trop long) remontent par champ ; les autres erreurs partent en toast et
 * sont relayées à l'appelant (jamais avalées silencieusement).
 */
export function usePublicSettings() {
    const { currentOrganization } = useOrganization();
    const organizationUuid = currentOrganization?.uuid ?? null;
    const { push } = useToast();

    const [settings, setSettings] = useState<OrganizationPublicSettings | null>(null);
    const [isLoading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const reload = useCallback(async () => {
        if (!organizationUuid) {
            setSettings(null);
            setLoading(false);

            return;
        }
        setLoading(true);
        setError(null);
        try {
            setSettings(await fetchPublicSettings(organizationUuid));
        } catch (cause) {
            setError(cause instanceof ApiError ? cause.message : 'Impossible de charger les réglages publics.');
        } finally {
            setLoading(false);
        }
    }, [organizationUuid]);

    useEffect(() => { void reload(); }, [reload]);

    /**
     * Enregistre les réglages publics. Les violations de validation (422)
     * sont renvoyées par champ à la page ; les autres erreurs sont signalées
     * par toast puis relancées.
     */
    const save = async (payload: ShowcasePayload): Promise<void> => {
        if (!organizationUuid) return;
        if (!settings) return;
        const changes = changedFields(payload, {
            slug: settings.slug ?? undefined,
            publicDescription: settings.publicDescription ?? '',
            isPubliclyListed: settings.isPubliclyListed,
        });
        if (Object.keys(changes).length === 0) return;
        try {
            setSettings(await updateShowcase(organizationUuid, changes));
            push('success', 'Les réglages publics ont été enregistrés.');
        } catch (cause) {
            if (!isValidationError(cause)) push('error', actionErrorMessage(cause));
            throw cause;
        }
    };

    const saveLogo = async (file: File): Promise<void> => {
        if (!organizationUuid) return;
        try {
            await uploadOrganizationLogo(organizationUuid, file);
            await reload();
            push('success', 'Le logo a été mis à jour.');
        } catch (cause) {
            push('error', actionErrorMessage(cause));
            throw cause;
        }
    };

    const removeLogo = async (): Promise<void> => {
        if (!organizationUuid) return;
        try {
            await deleteOrganizationLogo(organizationUuid);
            await reload();
            push('success', 'Le logo a été retiré.');
        } catch (cause) {
            push('error', actionErrorMessage(cause));
            throw cause;
        }
    };

    return {
        settings,
        isLoading,
        error,
        reload,
        save,
        saveLogo,
        removeLogo,
        fieldErrorMap,
    };
}
