import { useCallback, useEffect, useMemo, useState } from 'react';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { useAdminCity } from '../../../../app/providers/AdminCityProvider';
import { ApiError } from '../../../../../services/api/api.types';
import { fetchTreasuryData } from '../services/treasuryService';
import type { FinancialMovement, FinancialMovementKind, TreasuryData } from '../types/finance.types';

function localDate(date: Date): string {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function cents(amount: string): bigint {
    const match = /^(-?)(\d+)(?:\.(\d{0,2})\d*)?$/.exec(amount.trim());
    if (!match) return 0n;
    const value = BigInt(match[2]) * 100n + BigInt((match[3] ?? '').padEnd(2, '0') || '0');
    return match[1] ? -value : value;
}

function decimal(value: bigint): string {
    const sign = value < 0n ? '-' : '';
    const absolute = value < 0n ? -value : value;
    return `${sign}${absolute / 100n}.${String(absolute % 100n).padStart(2, '0')}`;
}

export function sumMovements(movements: FinancialMovement[], predicate: (movement: FinancialMovement) => boolean): Map<string, string> {
    const totals = new Map<string, bigint>();
    for (const movement of movements) {
        if (predicate(movement)) totals.set(movement.currency, (totals.get(movement.currency) ?? 0n) + cents(movement.amount));
    }
    return new Map([...totals].map(([currency, amount]) => [currency, decimal(amount)]));
}

export function useTreasury() {
    const { currentOrganization, organizationRole } = useOrganization();
    const { cities, activeCity, activeCityUuid, isLoading: cityLoading, selectCity } = useAdminCity();
    const organizationUuid = currentOrganization?.uuid ?? null;
    const cityUuid = organizationRole === 'admin_ville' ? activeCityUuid || null : undefined;
    const [from, setFrom] = useState(() => {
        const today = new Date();
        return localDate(new Date(today.getFullYear(), today.getMonth(), 1));
    });
    const [to, setTo] = useState(() => localDate(new Date()));
    const [data, setData] = useState<TreasuryData | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const reload = useCallback(async () => {
        if (!organizationUuid) {
            setData(null);
            setLoading(false);
            return;
        }
        if (organizationRole === 'admin_ville' && cityLoading) return;
        if (from > to) {
            setError('La date de début doit être antérieure ou égale à la date de fin.');
            setLoading(false);
            return;
        }
        if (!cityUuid && organizationRole === 'admin_ville') {
            setData({ movements: [], expenseTotal: 0, paymentTotal: 0, note: null });
            setLoading(false);
            return;
        }
        setLoading(true);
        setError(null);
        try {
            setData(await fetchTreasuryData(organizationUuid, from, to, cityUuid ?? undefined, activeCity?.name));
        } catch (cause) {
            setError(cause instanceof ApiError ? cause.message : 'Impossible de charger les mouvements financiers.');
        } finally {
            setLoading(false);
        }
    }, [organizationUuid, organizationRole, cityLoading, cityUuid, activeCity?.name, from, to]);

    useEffect(() => { void reload(); }, [reload]);

    const movements = data?.movements ?? [];
    const totals = useMemo(() => {
        const expenses = new Map<string, string>();
        for (const [currency, amount] of sumMovements(movements, (row) => row.kind === 'expense')) {
            expenses.set(currency, decimal(-cents(amount)));
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
