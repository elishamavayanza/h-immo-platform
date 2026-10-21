import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { useAdminCity } from '../../../../app/providers/AdminCityProvider';
import { ApiError } from '../../../../../services/api/api.types';
import { fetchTreasuryData } from '../services/treasuryService';
import type { FinancialMovementKind, TreasuryData } from '../types/finance.types';
import { negateDecimal, sumMovements } from '../utils/financeAmounts';

function localDate(date: Date): string {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

export { sumMovements } from '../utils/financeAmounts';

export function useTreasury(selectedCityUuid?: string) {
    const { currentOrganization, organizationRole } = useOrganization();
    const { cities, activeCity, activeCityUuid, isLoading: cityLoading, selectCity } = useAdminCity();
    const organizationUuid = currentOrganization?.uuid ?? null;
    const cityUuid = organizationRole === 'admin_ville' ? activeCityUuid || null : selectedCityUuid || undefined;
    const [from, setFrom] = useState(() => {
        const today = new Date();
        return localDate(new Date(today.getFullYear(), today.getMonth(), 1));
    });
    const [to, setTo] = useState(() => localDate(new Date()));
    const [data, setData] = useState<TreasuryData | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const requestId = useRef(0);

    const reload = useCallback(async () => {
        const currentRequestId = ++requestId.current;
        if (!organizationUuid) {
            setData(null);
            setError(null);
            setLoading(false);
            return;
        }
        if (organizationRole === 'admin_ville' && cityLoading) {
            setData(null);
            setError(null);
            setLoading(true);
            return;
        }
        if (from > to) {
            setData(null);
            setError('La date de début doit être antérieure ou égale à la date de fin.');
            setLoading(false);
            return;
        }
        if (!cityUuid && organizationRole === 'admin_ville') {
            setData({ movements: [], expenseTotal: 0, paymentTotal: 0, note: null });
            setError(null);
            setLoading(false);
            return;
        }
        setData(null);
        setLoading(true);
        setError(null);
        try {
            const nextData = await fetchTreasuryData(organizationUuid, from, to, cityUuid ?? undefined);
            if (currentRequestId === requestId.current) setData(nextData);
        } catch (cause) {
            if (currentRequestId === requestId.current) {
                setError(cause instanceof ApiError || cause instanceof Error ? cause.message : 'Impossible de charger les mouvements financiers.');
            }
        } finally {
            if (currentRequestId === requestId.current) setLoading(false);
        }
    }, [organizationUuid, organizationRole, cityLoading, cityUuid, activeCity?.name, from, to]);

    useEffect(() => {
        void reload();
        return () => { requestId.current++; };
    }, [reload]);

    const movements = data?.movements ?? [];
    const totals = useMemo(() => {
        const expenses = new Map<string, string>();
        for (const [currency, amount] of sumMovements(movements, (row) => row.kind === 'expense')) {
            expenses.set(currency, negateDecimal(amount));
        }
        return {
            income: sumMovements(movements, (row) => row.kind === 'income'),
            expenses,
            net: sumMovements(movements, () => true),
            cash: sumMovements(movements, (row) => row.method === 'cash'),
        };
    }, [movements]);

    return {
        organizationRole, cities, activeCity, cityLoading, cityUuid, selectCity,
        from, setFrom, to, setTo, data, movements, totals, loading, error, reload,
    };
}

export type { FinancialMovementKind };
