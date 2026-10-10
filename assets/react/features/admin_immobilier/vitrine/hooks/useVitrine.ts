import { useCallback, useEffect, useMemo, useState } from 'react';

import { useToast } from '../../../../app/layout/MainLayout/contexts/ToastContext';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { ApiError } from '../../../../../services/api/api.types';
import { fetchVitrine, setUnitPublished } from '../services/vitrineService';
import type { VitrineData, VitrineRow } from '../types/vitrine.types';

function actionErrorMessage(cause: unknown): string {
    return cause instanceof ApiError ? cause.message : 'Une erreur inattendue est survenue. Réessayez.';
}

/**
 * Chargement des annonces de l'organization active + bascule de publication.
 *
 * `PATCH …/publish` renvoie 422 si un bail actif occupe l'unité : le message
 * du backend est remonté tel quel, la liste étant rechargée pour refléter
 * l'état réel.
 */
export function useVitrine() {
    const { currentOrganization } = useOrganization();
    const organizationUuid = currentOrganization?.uuid ?? null;
    const { push } = useToast();

    const [data, setData] = useState<VitrineData | null>(null);
    const [isLoading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('all');
    const [pendingId, setPendingId] = useState<string | null>(null);

    const reload = useCallback(async () => {
        if (!organizationUuid) {
            setData(null);
            setLoading(false);

            return;
        }
        setLoading(true);
        setError(null);
        try {
            setData(await fetchVitrine(organizationUuid));
        } catch (cause) {
            setError(cause instanceof ApiError ? cause.message : 'Impossible de charger les annonces de l’organisation.');
        } finally {
            setLoading(false);
        }
    }, [organizationUuid]);

    useEffect(() => { void reload(); }, [reload]);

    const togglePublished = useCallback(async (row: VitrineRow) => {
        setPendingId(row.id);
        try {
            push('success', await setUnitPublished(row.id, !row.isPublished));
            await reload();
        } catch (cause) {
            push('error', actionErrorMessage(cause));
        } finally {
            setPendingId(null);
        }
    }, [reload, push]);

    const rows = useMemo(() => {
        const query = search.trim().toLocaleLowerCase('fr');
        return (data?.rows ?? []).filter((row) =>
            (!query || [row.title, row.city, row.type].some((value) => value.toLocaleLowerCase('fr').includes(query)))
            && (status === 'all'
                || (status === 'published' && row.isPublished)
                || (status === 'unpublished' && !row.isPublished)),
        );
    }, [data, search, status]);

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
        pendingId,
        togglePublished,
    };
}
