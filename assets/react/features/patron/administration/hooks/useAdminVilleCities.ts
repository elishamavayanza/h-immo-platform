import { useCallback, useEffect, useMemo, useState } from 'react';

import { useToast } from '../../../../app/layout/MainLayout/contexts/ToastContext';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { ApiError } from '../../../../../services/api/api.types';
import { actionErrorMessage } from '../../../shared/utils/actionErrors';
import { fetchCities } from '../../../admin_immobilier/shared/services/referenceService';
import { administrationService } from '../services/administrationService';
import type { AdminCityOption, UserCityLink } from '../types/administration.types';

/**
 * Périmètre géographique d'un ADMIN_VILLE (espace PATRON).
 *
 * Charge les villes de l'organization active (`GET /v1/property/cities`) et
 * les rattachements du membre ciblé (`GET /v1/identity/user-cities/user/{uuid}`),
 * puis expose `assign()` / `revoke()`. Le périmètre doit contenir au moins une
 * ville : l'UI empêche de retirer la dernière (le backend refuse un ADMIN_VILLE
 * sans ville à la création, mais l'entité `UserCity` reste supprimable).
 */
export function useAdminVilleCities(userUuid: string | null) {
    const { currentOrganization } = useOrganization();
    const organizationUuid = currentOrganization?.uuid ?? null;
    const { push } = useToast();

    const [cities, setCities] = useState<AdminCityOption[]>([]);
    const [links, setLinks] = useState<UserCityLink[]>([]);
    const [isLoading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const reload = useCallback(async () => {
        if (!organizationUuid || !userUuid) {
            setLinks([]);

            return;
        }
        setLoading(true);
        setError(null);
        try {
            const [available, assigned] = await Promise.all([
                fetchCities(organizationUuid),
                administrationService.listUserCities(userUuid),
            ]);
            setCities(available.items.map((city) => ({ uuid: city.id, name: city.name })));
            setLinks(assigned);
        } catch (cause) {
            setError(cause instanceof ApiError ? cause.message : 'Impossible de charger les villes de ce membre.');
        } finally {
            setLoading(false);
        }
    }, [organizationUuid, userUuid]);

    useEffect(() => { void reload(); }, [reload]);

    /** Villes rattachées, enrichies du libellé. */
    const assigned = useMemo(
        () => links.map((link) => ({
            link,
            city: cities.find((option) => option.uuid === link.cityId) ?? null,
        })),
        [links, cities],
    );

    /** Villes de l'organisation non encore rattachées, proposables à l'ajout. */
    const available = useMemo(
        () => cities.filter((city) => !links.some((link) => link.cityId === city.uuid)),
        [cities, links],
    );

    const assign = async (cityUuid: string): Promise<void> => {
        if (!userUuid) return;
        const cityName = cities.find((city) => city.uuid === cityUuid)?.name ?? 'la ville sélectionnée';
        try {
            await administrationService.assignUserCity(userUuid, cityUuid);
            await reload();
            push('success', `L’accès de l’administrateur a été attribué à ${cityName}.`);
        } catch (cause) {
            push('error', actionErrorMessage(cause));
            throw cause;
        }
    };

    const revoke = async (linkUuid: string): Promise<void> => {
        const cityName = links.find((link) => link.id === linkUuid)?.cityId;
        const cityLabel = cities.find((city) => city.uuid === cityName)?.name ?? 'la ville';
        try {
            await administrationService.revokeUserCity(linkUuid);
            await reload();
            push('success', `L’accès de l’administrateur à ${cityLabel} a été retiré.`);
        } catch (cause) {
            push('error', actionErrorMessage(cause));
            throw cause;
        }
    };

    const setPersonnelPermission = async (linkUuid: string, enabled: boolean): Promise<void> => {
        const cityId = links.find((link) => link.id === linkUuid)?.cityId;
        const cityName = cities.find((city) => city.uuid === cityId)?.name ?? 'cette ville';
        try {
            await administrationService.setUserCityPersonnelPermission(linkUuid, enabled);
            await reload();
            push('success', enabled
                ? `L’ajout de personnel est autorisé pour l’administrateur de ${cityName}.`
                : `L’autorisation d’ajouter du personnel à ${cityName} a été retirée.`);
        } catch (cause) {
            push('error', actionErrorMessage(cause));
            throw cause;
        }
    };

    return { assigned, available, isLoading, error, reload, assign, revoke, setPersonnelPermission };
}
