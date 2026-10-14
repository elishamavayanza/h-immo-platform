import { useCallback, useEffect, useState } from 'react';

import { useAdminCity } from '../../../../app/providers/AdminCityProvider';
import { ApiError } from '../../../../../services/api/api.types';
import { fetchAdminVilleReports } from '../services/reportsService';
import type { AdminVilleReportsData } from '../types';
import { computePeriodPreset } from '../../../shared/reports/periodPresets';

/**
 * Rapport ADMIN_VILLE : le périmètre vient du backend (villes affectées à
 * l'utilisateur via `useAssignedCities`). On demande le rapport de la ville
 * sélectionnée ; changer de ville ou de période recharge depuis l'API, sans
 * jamais filtrer localement (le backend reste seul juge de l'accès).
 */
export function useReports() {
    const { cities, activeCityUuid: cityUuid, selectCity: setCityUuid, isLoading: citiesLoading } = useAdminCity();
    const hasAssignedCities = cities.length > 0;
    const [periodFrom, setPeriodFrom] = useState(() => computePeriodPreset('this_year').from);
    const [periodTo, setPeriodTo] = useState(() => computePeriodPreset('this_year').to);
    const [data, setData] = useState<AdminVilleReportsData | null>(null);
    const [isLoading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const reload = useCallback(async () => {
        if (!cityUuid) {
            setData(null);
            setError(null);
            setLoading(false);

            return;
        }
        setLoading(true);
        setError(null);
        try {
            const result = await fetchAdminVilleReports(cityUuid, {
                periodFrom: periodFrom || undefined,
                periodTo: periodTo || undefined,
            });
            setData(result);
        } catch (cause) {
            setData(null);
            setError(cause instanceof ApiError ? cause.message : 'Impossible de charger le rapport de la ville.');
        } finally {
            setLoading(false);
        }
    }, [cityUuid, periodFrom, periodTo]);

    useEffect(() => { void reload(); }, [reload]);

    return {
        data,
        isLoading: isLoading || citiesLoading,
        error,
        reload,
        cities,
        cityUuid,
        setCityUuid,
        periodFrom,
        setPeriodFrom,
        periodTo,
        setPeriodTo,
        citiesLoading,
        hasAssignedCities,
    };
}
