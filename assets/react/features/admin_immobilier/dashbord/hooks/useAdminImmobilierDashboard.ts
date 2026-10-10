import { useCallback, useEffect, useState } from 'react';

import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { ApiError } from '../../../../../services/api/api.types';
import { fetchAdminImmobilierDashboard } from '../services/adminImmobilierDashboardService';
import type { AdminImmobilierDashboardData } from '../types/adminImmobilierDashboard.types';

/**
 * Charge les indicateurs de l'organization active. Le rapport étant borné
 * par le backend à l'appelant, aucun filtre local n'est appliqué : un
 * changement d'organization (ou de rôle) recharge le tableau de bord.
 */
export function useAdminImmobilierDashboard() {
    const { currentOrganization } = useOrganization();
    const organizationUuid = currentOrganization?.uuid ?? null;

    const [data, setData] = useState<AdminImmobilierDashboardData | null>(null);
    const [isLoading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const reload = useCallback(async () => {
        if (!organizationUuid) {
            setData(null);
            setLoading(false);

            return;
        }
        setLoading(true);
        setError(null);
        try {
            const result = await fetchAdminImmobilierDashboard(organizationUuid);
            setData(result);
        } catch (cause) {
            setError(cause instanceof ApiError ? cause.message : 'Impossible de charger le tableau de bord.');
        } finally {
            setLoading(false);
        }
    }, [organizationUuid]);

    useEffect(() => { void reload(); }, [reload]);

    return { data, isLoading, error, reload };
}