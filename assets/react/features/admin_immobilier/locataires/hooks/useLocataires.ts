import { useCallback, useEffect, useMemo, useState } from 'react';

import { useToast } from '../../../../app/layout/MainLayout/contexts/ToastContext';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { ApiError } from '../../../../../services/api/api.types';
import { archiveTenant, fetchLocataires } from '../services/locatairesService';
import type { LocataireRow, LocatairesData } from '../types/locataire.types';

function actionErrorMessage(cause: unknown): string {
    return cause instanceof ApiError ? cause.message : 'Une erreur inattendue est survenue. Réessayez.';
}

/**
 * Chargement des locataires de l'organization active + filtrage local
 * (recherche, type) sur le jeu chargé, et archivage d'un locataire.
 *
 * L'archivage passe par `PATCH …/archive` (soft delete côté backend) : après
 * succès on recharge la liste, le locataire archivé disparaissant de la
 * requête serveur (`deletedAt IS NULL`). En cas d'échec (409 déjà archivé,
 * 403, 404), le message de l'API est remonté tel quel au toast.
 */
export function useLocataires() {
    const { currentOrganization } = useOrganization();
    const organizationUuid = currentOrganization?.uuid ?? null;
    const { push } = useToast();

    const [data, setData] = useState<LocatairesData | null>(null);
    const [isLoading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const [search, setSearch] = useState('');
    const [type, setType] = useState('all');

    const [pending, setPending] = useState<LocataireRow | null>(null);
    const [isArchiving, setArchiving] = useState(false);

    const reload = useCallback(async () => {
        if (!organizationUuid) {
            setData(null);
            setLoading(false);

            return;
        }
        setLoading(true);
        setError(null);
        try {
            setData(await fetchLocataires(organizationUuid));
        } catch (cause) {
            setError(cause instanceof ApiError ? cause.message : 'Impossible de charger les locataires de l’organisation.');
        } finally {
            setLoading(false);
        }
    }, [organizationUuid]);

    useEffect(() => { void reload(); }, [reload]);

    const archive = useCallback(async () => {
        if (!pending) return;
        setArchiving(true);
        try {
            const message = await archiveTenant(pending.id);
            push('success', message);
            await reload();
        } catch (cause) {
            push('error', actionErrorMessage(cause));
        } finally {
            setArchiving(false);
            setPending(null);
        }
    }, [pending, reload, push]);

    const rows = useMemo(() => {
        const query = search.trim().toLocaleLowerCase('fr');
        return (data?.rows ?? []).filter((row) =>
            (!query || [row.name, row.sublabel, row.unitReference ?? ''].some((value) => value.toLocaleLowerCase('fr').includes(query)))
            && (type === 'all' || row.type === type),
        );
    }, [data, search, type]);

    return {
        data,
        rows,
        isLoading,
        error,
        reload,
        search,
        setSearch,
        type,
        setType,
        pending,
        requestArchive: setPending,
        cancelArchive: () => setPending(null),
        confirmArchive: archive,
        isArchiving,
    };
}
