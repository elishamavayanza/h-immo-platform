import { useCallback, useEffect, useState } from 'react';

import { ApiError } from '../../../../../services/api/api.types';
import { useAssignedCities } from '../../../shared/hooks/useAssignedCities';
import { fetchAdminVilleDashboard } from '../services/adminVilleDashboardService';
import type { AdminVilleDashboardData } from '../types/adminVilleDashboard.types';

/**
 * Charge les indicateurs de la ville sélectionnée. Le périmètre vient du
 * backend (`useAssignedCities`) : impossible de charger une ville non
 * affectée, l'API refuserait en 403. Changer de ville recharge l'API.
 */
export function useAdminVilleDashboard() {
    const { cities, isLoading: citiesLoading, hasAssignedCities } = useAssignedCities();

    const [cityUuid, setCityUuid] = useState('');
    const [data, setData] = useState<AdminVilleDashboardData | null>(null);
    const [isLoading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // Ville par défaut : la première ville affectée renvoyée par l'API.
    useEffect(() => {
        if (!cityUuid && cities.length > 0) setCityUuid(cities[0].id);
    }, [cities, cityUuid]);

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
            const result = await fetchAdminVilleDashboard(cityUuid);
            setData(result);
        } catch (cause) {
            setData(null);
            setError(cause instanceof ApiError ? cause.message : 'Impossible de charger le tableau de bord.');
        } finally {
            setLoading(false);
        }
    }, [cityUuid]);

    useEffect(() => { void reload(); }, [reload]);

    return {
        data,
        isLoading: isLoading || citiesLoading,
        error,
        reload,
        cities,
        cityUuid,
        setCityUuid,
        citiesLoading,
        hasAssignedCities,
    };
}