import { useCallback, useEffect, useMemo, useState } from 'react';

import { useToast } from '../../../../app/layout/MainLayout/contexts/ToastContext';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { ApiError } from '../../../../../services/api/api.types';
import {
    cancelExpense,
    createExpense,
    fetchDepenses,
    updateExpense,
    type CreateExpensePayload,
    type UpdateExpensePayload,
} from '../services/depensesService';
import type { DepensesData } from '../types/depense.types';

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
 * Chargement des dépenses de l'organization active + actions d'écriture.
 *
 * Le périmètre (organisations et villes accessibles) est appliqué par le
 * backend ; la recherche texte et le filtre de catégorie restent locaux sur
 * le jeu chargé.
 */
export function useDepenses() {
    const { currentOrganization } = useOrganization();
    const organizationUuid = currentOrganization?.uuid ?? null;
    const { push } = useToast();

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

    const createExpenseAction = useCallback(async (payload: CreateExpensePayload): Promise<void> => {
        await runAction(push, 'Dépense enregistrée.', () => createExpense(payload));
        await reload();
    }, [push, reload]);

    const updateExpenseAction = useCallback(async (expenseUuid: string, payload: UpdateExpensePayload): Promise<void> => {
        await runAction(push, 'Dépense mise à jour.', () => updateExpense(expenseUuid, payload));
        await reload();
    }, [push, reload]);

    const cancelExpenseAction = useCallback(async (expenseUuid: string, reason: string): Promise<void> => {
        await runAction(push, 'Dépense annulée.', () => cancelExpense(expenseUuid, reason));
        await reload();
    }, [push, reload]);

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
        createExpense: createExpenseAction,
        updateExpense: updateExpenseAction,
        cancelExpense: cancelExpenseAction,
    };
}