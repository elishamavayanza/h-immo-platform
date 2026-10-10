import { useCallback, useEffect, useMemo, useState } from 'react';

import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { ApiError } from '../../../../../services/api/api.types';
import { fetchPersonnel } from '../services/personnelService';
import type { PersonnelData } from '../types/personnel.types';

/**
 * Chargement du personnel de l'organization active.
 *
 * Le périmètre (organisations et villes accessibles) est appliqué par le
 * backend ; la recherche et le filtre de ville restent locaux sur le jeu
 * chargé.
 */
export function usePersonnel() {
    const { currentOrganization } = useOrganization();
    const organizationUuid = currentOrganization?.uuid ?? null;

    const [data, setData] = useState<PersonnelData | null>(null);
    const [isLoading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const [search, setSearch] = useState('');
    const [city, setCity] = useState('all');

    const reload = useCallback(async () => {
        if (!organizationUuid) {
            setData(null);
            setLoading(false);

            return;
        }
        setLoading(true);
        setError(null);
        try {
            setData(await fetchPersonnel(organizationUuid));
        } catch (cause) {
            setError(cause instanceof ApiError ? cause.message : 'Impossible de charger le personnel de l’organisation.');
        } finally {
            setLoading(false);
        }
    }, [organizationUuid]);

    useEffect(() => { void reload(); }, [reload]);

    const availableCities = useMemo(
        () => [...new Set((data?.rows ?? []).map((row) => row.city).filter((value) => value !== '—'))],
        [data],
    );

    const rows = useMemo(() => {
        const query = search.trim().toLocaleLowerCase('fr');
        return (data?.rows ?? []).filter((row) =>
            (!query || [row.name, row.role, row.city].some((value) => value.toLocaleLowerCase('fr').includes(query)))
            && (city === 'all' || row.city === city),
        );
    }, [data, search, city]);

    return {
        data,
        rows,
        availableCities,
        isLoading,
        error,
        reload,
        search,
        setSearch,
        city,
        setCity,
    };
}
