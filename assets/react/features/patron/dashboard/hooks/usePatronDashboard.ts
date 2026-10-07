import { useEffect, useState } from 'react';
import { fetchPatronDashboard } from '../services/patronDashboardService';
import type { PatronDashboardData } from '../types/patronDashboard.types';

export function usePatronDashboard() {
    const [data, setData] = useState<PatronDashboardData | null>(null);
    const [isLoading, setLoading] = useState(true);
    useEffect(() => { let active = true; fetchPatronDashboard().then((result) => { if (active) setData(result); }).finally(() => { if (active) setLoading(false); }); return () => { active = false; }; }, []);
    return { data, isLoading };
}
