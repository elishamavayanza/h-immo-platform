import { useCallback, useEffect, useMemo, useState } from 'react';

import { useToast } from '../../../../app/layout/MainLayout/contexts/ToastContext';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { ApiError } from '../../../../../services/api/api.types';
import {
    createAssignment,
    createWorker,
    endAssignment,
    fetchPersonnel,
    updateAssignment,
    updateWorker,
    type CreateAssignmentPayload,
    type CreateWorkerPayload,
    type UpdateAssignmentPayload,
    type UpdateWorkerPayload,
} from '../services/personnelService';
import type { PersonnelData } from '../types/personnel.types';

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
 * Chargement du personnel de l'organization active + actions d'écriture.
 *
 * Le périmètre (organisations et villes accessibles) est appliqué par le
 * backend ; la recherche et le filtre de ville restent locaux sur le jeu
 * chargé.
 */
export function usePersonnel() {
    const { currentOrganization } = useOrganization();
    const organizationUuid = currentOrganization?.uuid ?? null;
    const { push } = useToast();

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

    const createWorkerAction = useCallback(async (payload: CreateWorkerPayload) => {
        const worker = await createWorker(payload);
        push('success', 'Ouvrier créé.');
        await reload();

        return worker;
    }, [push, reload]);

    const updateWorkerAction = useCallback(async (workerUuid: string, payload: UpdateWorkerPayload): Promise<void> => {
        await runAction(push, 'Ouvrier mis à jour.', () => updateWorker(workerUuid, payload));
        await reload();
    }, [push, reload]);

    const createAssignmentAction = useCallback(async (payload: CreateAssignmentPayload): Promise<void> => {
        await runAction(push, 'Affectation créée.', () => createAssignment(payload));
        await reload();
    }, [push, reload]);

    const updateAssignmentAction = useCallback(async (assignmentUuid: string, payload: UpdateAssignmentPayload): Promise<void> => {
        await runAction(push, 'Affectation mise à jour.', () => updateAssignment(assignmentUuid, payload));
        await reload();
    }, [push, reload]);

    const endAssignmentAction = useCallback(async (assignmentUuid: string, endDate: string): Promise<void> => {
        await runAction(push, 'Affectation terminée.', () => endAssignment(assignmentUuid, endDate));
        await reload();
    }, [push, reload]);

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
        createWorker: createWorkerAction,
        updateWorker: updateWorkerAction,
        createAssignment: createAssignmentAction,
        updateAssignment: updateAssignmentAction,
        endAssignment: endAssignmentAction,
    };
}
