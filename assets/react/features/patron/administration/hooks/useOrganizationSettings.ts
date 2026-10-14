import { useCallback, useEffect, useState } from 'react';

import { useToast } from '../../../../app/layout/MainLayout/contexts/ToastContext';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { ApiError } from '../../../../../services/api/api.types';
import { actionErrorMessage, isValidationError } from '../../../shared/utils/actionErrors';
import { changedFields } from '../../../shared/utils/changedFields';
import { administrationService } from '../services/administrationService';
import type { OrganizationProfile, UpdateOrganizationPayload } from '../types/administration.types';

/**
 * Coordonnées de l'organisation (espace PATRON).
 *
 * Charge la fiche de l'organization active (`GET …/organizations/{uuid}`) et
 * expose `save()` (`PUT …/organizations/{uuid}`). Les violations de validation
 * (422) sont relayées à la page pour affichage par champ ; les autres erreurs
 * partent en toast. La vitrine (slug/description/logo) est gérée ailleurs
 * (page Vitrine) : cette carte ne touche qu'aux coordonnées.
 */
export function useOrganizationSettings() {
    const { currentOrganization } = useOrganization();
    const organizationUuid = currentOrganization?.uuid ?? null;
    const { push } = useToast();

    const [profile, setProfile] = useState<OrganizationProfile | null>(null);
    const [isLoading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const reload = useCallback(async () => {
        if (!organizationUuid) {
            setProfile(null);
            setLoading(false);

            return;
        }
        setLoading(true);
        setError(null);
        try {
            setProfile(await administrationService.fetchOrganization(organizationUuid));
        } catch (cause) {
            setError(cause instanceof ApiError ? cause.message : 'Impossible de charger les coordonnées de l’organisation.');
        } finally {
            setLoading(false);
        }
    }, [organizationUuid]);

    useEffect(() => { void reload(); }, [reload]);

    const save = async (payload: UpdateOrganizationPayload): Promise<void> => {
        if (!organizationUuid) return;
        if (!profile) return;
        const changes = changedFields(payload, {
            name: profile.name.trim(),
            email: profile.email.trim(),
            phone: profile.phone.trim(),
            address: profile.address?.trim() ?? '',
            city: profile.city?.trim() ?? '',
            country: profile.country?.trim() ?? '',
        });
        if (Object.keys(changes).length === 0) return;
        try {
            setProfile(await administrationService.updateOrganization(organizationUuid, changes));
            push('success', 'Les coordonnées de l’organisation ont été enregistrées.');
        } catch (cause) {
            if (!isValidationError(cause)) push('error', actionErrorMessage(cause));
            throw cause;
        }
    };

    return { profile, isLoading, error, reload, save };
}
