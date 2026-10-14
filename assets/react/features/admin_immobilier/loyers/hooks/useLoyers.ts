import { useCallback, useEffect, useMemo, useState } from 'react';

import { useToast } from '../../../../app/layout/MainLayout/contexts/ToastContext';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { useAdminCity } from '../../../../app/providers/AdminCityProvider';
import { ApiError } from '../../../../../services/api/api.types';
import { canDo } from '../../../shared/permissions';
import {
    fetchLoyerPaymentActivities,
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
    const { currentOrganization, organizationRole } = useOrganization();
    const { activeCityUuid } = useAdminCity();
    const selectedCityUuid = organizationRole === 'admin_ville' ? activeCityUuid : null;
    const organizationUuid = currentOrganization?.uuid ?? null;
    const canViewPayments = canDo(organizationRole, 'view_payment');
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

    const loadPeriod = useCallback(async (from: string, to: string) => {
        if (!organizationUuid) return null;
        const start = new Date(`${from}T00:00:00`);
        const end = new Date(`${to}T23:59:59.999`);
        const [rents, paymentPage] = await Promise.all([
            fetchLoyers(organizationUuid, 'all', from, to),
            canViewPayments
                ? fetchLoyerPaymentActivities(organizationUuid, start.toISOString(), end.toISOString()).catch(() => null)
                : Promise.resolve(null),
        ]);
        const selectedRents = selectedCityUuid
            ? { ...rents, rows: rents.rows.filter((rent) => rent.cityId === selectedCityUuid) }
            : rents;
        const selectedRentIds = new Set(selectedRents.rows.map((rent) => rent.id));
        return {
            rents: selectedRents,
            payments: selectedCityUuid
                ? (paymentPage?.items ?? []).filter((payment) => selectedRentIds.has(payment.rentId))
                : paymentPage?.items ?? [],
            paymentsTotal: paymentPage?.total ?? 0,
            paymentsUnavailable: canViewPayments && paymentPage === null,
        };
    }, [organizationUuid, canViewPayments, selectedCityUuid]);

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
            (!selectedCityUuid || row.cityId === selectedCityUuid)
            && (!query || [row.tenant, row.unitLabel, row.periodLabel].some((value) => value.toLocaleLowerCase('fr').includes(query))),
        );
    }, [data, search, selectedCityUuid]);

    return {
        data,
        rows,
        isLoading,
        error,
        reload,
        loadPeriod,
        search,
        setSearch,
        status,
        setStatus,
        recordPayment: recordPaymentAction,
        markOverdue: markOverdueAction,
        updateRent: updateRentAction,
    };
}
