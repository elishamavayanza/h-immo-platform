// ============================================================
// Hook d'orchestration : charge les données du tableau de bord.
// Pattern stable : { data, isLoading, error, reload }.
// ============================================================

import { useCallback, useEffect, useRef, useState } from 'react';

import { fetchSuperAdminDashboard } from '../services';
import {SuperAdminDashboardData} from "../types/superAdminDashboard.types.ts";

export interface UseSuperAdminDashboardResult {
    data: SuperAdminDashboardData | null;
    isLoading: boolean;
    error: Error | null;
    reload: () => void;
}

export function useSuperAdminDashboard(): UseSuperAdminDashboardResult {
    const [data, setData] = useState<SuperAdminDashboardData | null>(null);
    const [isLoading, setLoading] = useState(true);
    const [error, setError] = useState<Error | null>(null);
    const [tick, setTick] = useState(0);
    const cancelled = useRef(false);

    useEffect(() => {
        cancelled.current = false;
        setLoading(true);

        fetchSuperAdminDashboard()
            .then((payload) => {
                if (cancelled.current) return;
                setData(payload);
                setError(null);
            })
            .catch((err: unknown) => {
                if (cancelled.current) return;
                setError(err instanceof Error ? err : new Error('Erreur inconnue'));
            })
            .finally(() => {
                if (cancelled.current) return;
                setLoading(false);
            });

        return () => {
            cancelled.current = true;
        };
    }, [tick]);

    const reload = useCallback(() => setTick((n) => n + 1), []);

    return { data, isLoading, error, reload };
}
