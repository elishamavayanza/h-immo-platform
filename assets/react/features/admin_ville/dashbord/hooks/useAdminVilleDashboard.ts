import { useEffect, useState } from 'react';
import { useAuth } from '../../../../app/providers/AuthProvider';
import { buildAdminVilleDashboard } from '../services/adminVilleDashboardService';
import type { AdminVilleDashboardData } from '../types/adminVilleDashboard.types';
export function useAdminVilleDashboard() {
    const { user } = useAuth();
    const cities = user?.cities.map((item) => item.name) ?? [];
    const cityKey = cities.join('|');
    const [data, setData] = useState<AdminVilleDashboardData | null>(null);
    const [isLoading, setLoading] = useState(true);
    useEffect(() => {
        let mounted = true;
        Promise.resolve(buildAdminVilleDashboard(cities)).then((result) => { if (mounted) setData(result); }).finally(() => { if (mounted) setLoading(false); });
        return () => { mounted = false; };
    }, [cityKey]);
    return { data, isLoading };
}
