import { useCallback, useEffect, useMemo, useState } from 'react';

import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { ApiError } from '../../../../../services/api/api.types';
import { fetchDepenses } from '../services/depensesService';
import type { DepensesData } from '../types/depense.types';

/**
 * Chargement des dépenses de l'organization active.
 *
 * Le périmètre (organisations et villes accessibles) est appliqué par le
 * backend ; la recherche texte et le filtre de catégorie restent locaux sur
 * le jeu chargé.
 */
export function useDepenses() {
    const { currentOrganization } = useOrganization();
    const organizationUuid = currentOrganization?.uuid ?? null;

    const [data, setData] = useState<DepensesData | null>(null);
    const [isLoading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const [search, setSearch] = useState('');
    const [category, setCategory] = useState('all');

    const reload = useCallback(async () => {
        if (!organizationUuid) {
            setData(null);
            setLoading(false);

            return;
        }
        setLoading(true);
        setError(null);
        try {
            setData(await fetchDepenses(organizationUuid));
        } catch (cause) {
            setError(cause instanceof ApiError ? cause.message : 'Impossible de charger les dépenses de l’organisation.');
        } finally {
            setLoading(false);
        }
    }, [organizationUuid]);

    useEffect(() => { void reload(); }, [reload]);

    const availableCategories = useMemo(
        () => [...new Map((data?.rows ?? []).map((row) => [row.categoryCode, row.category])).entries()],
        [data],
    );

    const rows = useMemo(() => {
        const query = search.trim().toLocaleLowerCase('fr');
        return (data?.rows ?? []).filter((row) =>
            (!query || [row.category, row.property, row.description, row.city].some((value) => value.toLocaleLowerCase('fr').includes(query)))
            && (category === 'all' || row.categoryCode === category),
        );
    }, [data, search, category]);

    return {
        data,
        rows,
        availableCategories,
        isLoading,
        error,
        reload,
        search,
        setSearch,
        category,
        setCategory,
    };
}
