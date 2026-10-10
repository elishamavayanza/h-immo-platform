import { useCallback, useEffect, useMemo, useState } from 'react';

import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { ApiError } from '../../../../../services/api/api.types';
import { fetchLoyers } from '../services/loyersService';
import type { LoyersData } from '../types/loyer.types';

/**
 * Chargement des échéances de l'organization active.
 *
 * Le filtre de statut est serveur (les valeurs `overdue` sont dérivées côté
 * backend) : changer de statut relance la requête. La recherche texte, elle,
 * reste locale sur le jeu chargé.
 */
export function useLoyers() {
    const { currentOrganization } = useOrganization();
    const organizationUuid = currentOrganization?.uuid ?? null;

    const [data, setData] = useState<LoyersData | null>(null);
    const [isLoading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('all');

    const reload = useCallback(async () => {
        if (!organizationUuid) {
            setData(null);
            setLoading(false);

            return;
        }
        setLoading(true);
        setError(null);
        try {
            setData(await fetchLoyers(organizationUuid, status));
        } catch (cause) {
            setError(cause instanceof ApiError ? cause.message : 'Impossible de charger les échéances de l’organisation.');
        } finally {
            setLoading(false);
        }
    }, [organizationUuid, status]);

    useEffect(() => { void reload(); }, [reload]);

    const rows = useMemo(() => {
        const query = search.trim().toLocaleLowerCase('fr');
        return (data?.rows ?? []).filter((row) =>
            !query || [row.tenant, row.unitLabel, row.periodLabel].some((value) => value.toLocaleLowerCase('fr').includes(query)),
        );
    }, [data, search]);

    return {
        data,
        rows,
        isLoading,
        error,
        reload,
        search,
        setSearch,
        status,
        setStatus,
    };
}
