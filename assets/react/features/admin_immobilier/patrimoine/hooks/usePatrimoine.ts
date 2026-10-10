import { useCallback, useEffect, useMemo, useState } from 'react';

import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { ApiError } from '../../../../../services/api/api.types';
import { fetchPatrimoine } from '../services/patrimoineService';
import type { PatrimoineData } from '../types/patrimoine.types';

/**
 * Chargement du patrimoine de l'organization active (catalogue + rapport),
 * puis filtrage local (recherche, ville, type) sur le jeu chargé.
 *
 * Les filtres ne déclenchent aucun nouvel appel : les listes API sont
 * bornées à REFERENCE_LIMIT, et le backend ne propose pas de `search` sur
 * ces endpoints — filtrer côté client sur ce qui est déjà chargé reste plus
 * honnête qu'une pagination qui ferait croire à une liste complète.
 */
export function usePatrimoine() {
    const { currentOrganization } = useOrganization();
    const organizationUuid = currentOrganization?.uuid ?? null;

    const [data, setData] = useState<PatrimoineData | null>(null);
    const [isLoading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const [search, setSearch] = useState('');
    const [city, setCity] = useState('all');
    const [kind, setKind] = useState('all');

    const reload = useCallback(async () => {
        if (!organizationUuid) {
            setData(null);
            setLoading(false);

            return;
        }
        setLoading(true);
        setError(null);
        try {
            setData(await fetchPatrimoine(organizationUuid));
        } catch (cause) {
            setError(cause instanceof ApiError ? cause.message : 'Impossible de charger le patrimoine de l’organisation.');
        } finally {
            setLoading(false);
        }
    }, [organizationUuid]);

    useEffect(() => { void reload(); }, [reload]);

    const rows = useMemo(() => {
        const query = search.trim().toLocaleLowerCase('fr');
        return (data?.rows ?? []).filter((row) =>
            (!query || [row.name, row.sublabel, row.city].some((value) => value.toLocaleLowerCase('fr').includes(query)))
            && (city === 'all' || row.city === city)
            && (kind === 'all' || row.kind === kind),
        );
    }, [data, search, city, kind]);

    return {
        data,
        rows,
        isLoading,
        error,
        reload,
        search,
        setSearch,
        city,
        setCity,
        kind,
        setKind,
    };
}
