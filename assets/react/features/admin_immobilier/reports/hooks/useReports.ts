import { useCallback, useEffect, useState } from 'react';

import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { ApiError } from '../../../../../services/api/api.types';
import { fetchCities } from '../../shared/services/referenceService';
import { fetchAdminImmobilierReports } from '../services/reportsService';
import type { AdminImmobilierReportsData } from '../types';
import { computePeriodPreset } from '../../../shared/reports/periodPresets';

/** Option du sélecteur de ville (borné aux villes de l'organisation active). */
export interface AdminImmobilierCityOption {
    value: string;
    label: string;
}

/**
 * Rapport ADMIN_IMMOBILIER : recharge le rapport de l'organisation active à
 * chaque bascule d'organisation, de période ou de ville. Le périmètre vient
 * du backend (le rôle et l'accès sont résolus côté serveur) ; aucun filtrage
 * local n'est appliqué.
 */
export function useReports() {
    const { currentOrganization } = useOrganization();
    const organizationUuid = currentOrganization?.uuid ?? null;

    const [periodFrom, setPeriodFrom] = useState(() => computePeriodPreset('this_year').from);
    const [periodTo, setPeriodTo] = useState(() => computePeriodPreset('this_year').to);
    const [cityUuid, setCityUuid] = useState('');
    const [cities, setCities] = useState<AdminImmobilierCityOption[]>([]);

    const [data, setData] = useState<AdminImmobilierReportsData | null>(null);
    const [isLoading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    // Villes de l'organisation : alimente le filtre. Une erreur de chargement
    // ne bloque pas le rapport (le filtre reste sans options).
    useEffect(() => {
        let active = true;
        if (!organizationUuid) {
            setCities([]);

            return () => { active = false; };
        }
        fetchCities(organizationUuid)
            .then((page) => {
                if (active) setCities(page.items.map((city) => ({ value: city.id, label: city.name })));
            })
            .catch(() => {
                if (active) setCities([]);
            });

        return () => { active = false; };
    }, [organizationUuid]);

    const reload = useCallback(async () => {
        if (!organizationUuid) {
            setData(null);
            setLoading(false);

            return;
        }
        setLoading(true);
        setError(null);
        try {
            const result = await fetchAdminImmobilierReports(organizationUuid, {
                periodFrom: periodFrom || undefined,
                periodTo: periodTo || undefined,
                cityUuid: cityUuid || undefined,
            });
            setData(result);
        } catch (cause) {
            setData(null);
            setError(cause instanceof ApiError ? cause.message : 'Impossible de charger le rapport de l’organisation.');
        } finally {
            setLoading(false);
        }
    }, [organizationUuid, periodFrom, periodTo, cityUuid]);

    useEffect(() => { void reload(); }, [reload]);

    return {
        data,
        isLoading,
        error,
        reload,
        cities,
        cityUuid,
        setCityUuid,
        periodFrom,
        setPeriodFrom,
        periodTo,
        setPeriodTo,
    };
}
