import { useCallback, useEffect, useState } from 'react';

import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { ApiError } from '../../../../../services/api/api.types';
import { fetchAdminImmobilierReports } from '../services/reportsService';
import type { AdminImmobilierReportsData } from '../types';

/**
 * Graphique du rapport ADMIN_IMMOBILIER : recharge le rapport de
 * l'organization active à chaque bascule (le rôle et le périmètre viennent
 * du contexte résolu, pas d'un cache client).
 */
export function useReports() {
    const { currentOrganization } = useOrganization();
    const organizationUuid = currentOrganization?.uuid ?? null;

    const [data, setData] = useState<AdminImmobilierReportsData | null>(null);
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
            const result = await fetchAdminImmobilierReports(organizationUuid);
            setData(result);
        } catch (cause) {
            setError(cause instanceof ApiError ? cause.message : 'Impossible de charger le rapport de l’organisation.');
        } finally {
            setLoading(false);
        }
    }, [organizationUuid]);

    useEffect(() => { void reload(); }, [reload]);

    return { data, isLoading, error, reload };
}