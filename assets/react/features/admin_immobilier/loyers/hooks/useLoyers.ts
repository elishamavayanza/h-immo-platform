import { useCallback, useEffect, useMemo, useState } from 'react';

import { useToast } from '../../../../app/layout/MainLayout/contexts/ToastContext';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { ApiError } from '../../../../../services/api/api.types';
import {
    fetchLoyers,
    markRentOverdue,
    recordPayment,
    updateRent,
    type RecordPaymentPayload,
    type UpdateRentPayload,
} from '../services/loyersService';
import type { LoyersData } from '../types/loyer.types';

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
 * Chargement des échéances de l'organization active + actions d'écriture.
 *
 * Le filtre de statut est serveur (les valeurs `overdue` sont dérivées côté
 * backend) : changer de statut relance la requête. La recherche texte, elle,
 * reste locale sur le jeu chargé.
 */
export function useLoyers() {
    const { currentOrganization } = useOrganization();
    const organizationUuid = currentOrganization?.uuid ?? null;
    const { push } = useToast();

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

    const recordPaymentAction = useCallback(async (payload: RecordPaymentPayload): Promise<void> => {
        await runAction(push, 'Paiement enregistré.', () => recordPayment(payload));
        await reload();
    }, [push, reload]);

    const markOverdueAction = useCallback(async (rentUuid: string): Promise<void> => {
        await runAction(push, 'Échéance marquée comme impayée.', () => markRentOverdue(rentUuid));
        await reload();
    }, [push, reload]);

    const updateRentAction = useCallback(async (rentUuid: string, payload: UpdateRentPayload): Promise<void> => {
        await runAction(push, 'Échéance mise à jour.', () => updateRent(rentUuid, payload));
        await reload();
    }, [push, reload]);

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
        recordPayment: recordPaymentAction,
        markOverdue: markOverdueAction,
        updateRent: updateRentAction,
    };
}