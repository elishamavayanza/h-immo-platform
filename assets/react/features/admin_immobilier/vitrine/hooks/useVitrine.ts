import { useCallback, useEffect, useMemo, useState } from 'react';

import { useToast } from '../../../../app/layout/MainLayout/contexts/ToastContext';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { ApiError } from '../../../../../services/api/api.types';
import {
    addUnitPhoto,
    fetchVitrine,
    removeUnitPhoto,
    setUnitPublished,
    updateUnit,
    type UpdateUnitPayload,
} from '../services/vitrineService';
import type { VitrineData, VitrineRow } from '../types/vitrine.types';

function actionErrorMessage(cause: unknown): string {
    return cause instanceof ApiError ? cause.message : 'Une erreur inattendue est survenue. Réessayez.';
}

function isValidationError(cause: unknown): boolean {
    return cause instanceof ApiError && cause.status === 422;
}

const runAction = async (push: ReturnType<typeof useToast>['push'], successMessage: string, action: () => Promise<unknown>): Promise<void> => {
    try {
        await action();
        push('success', successMessage);
    } catch (cause) {
        if (!isValidationError(cause)) push('error', actionErrorMessage(cause));
        throw cause;
    }
};

/**
 * Chargement des annonces de l'organization active + actions d'écriture.
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
            await runAction(push, row.isPublished
                ? `L’annonce « ${row.title} » a été retirée de la vitrine.`
                : `L’annonce « ${row.title} » est maintenant publiée sur la vitrine.`, () => setUnitPublished(row.id, !row.isPublished));
            await reload();
        } catch (cause) {
            // toast already shown by runAction
        } finally {
            setPendingId(null);
        }
    }, [push, reload]);

    const updateUnitAction = useCallback(async (unitUuid: string, payload: UpdateUnitPayload): Promise<void> => {
        const title = data?.rows.find((row) => row.id === unitUuid)?.title;
        await runAction(push, title ? `L’annonce « ${title} » a été mise à jour.` : 'L’annonce de l’unité a été mise à jour.', () => updateUnit(unitUuid, payload));
        await reload();
    }, [push, reload, data]);

    const addPhotoAction = useCallback(async (unitUuid: string, file: File): Promise<void> => {
        const title = data?.rows.find((row) => row.id === unitUuid)?.title;
        await runAction(push, title
            ? `La photo « ${file.name} » a été ajoutée à l’annonce « ${title} ».`
            : `La photo « ${file.name} » a été ajoutée à l’annonce de l’unité.`, () => addUnitPhoto(unitUuid, file));
        await reload();
    }, [push, reload, data]);

    const removePhotoAction = useCallback(async (unitUuid: string, photoUuid: string): Promise<void> => {
        const title = data?.rows.find((row) => row.id === unitUuid)?.title;
        await runAction(push, title
            ? `Une photo a été retirée de l’annonce « ${title} ».`
            : 'Une photo a été retirée de l’annonce de l’unité.', () => removeUnitPhoto(unitUuid, photoUuid));
        await reload();
    }, [push, reload, data]);

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
        updateUnit: updateUnitAction,
        addPhoto: addPhotoAction,
        removePhoto: removePhotoAction,
    };
}
