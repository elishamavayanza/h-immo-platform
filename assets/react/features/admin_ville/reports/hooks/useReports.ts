import { useMemo } from 'react';
import { useAuth } from '../../../../app/providers/AuthProvider';
import { useReportsData } from '../../../shared/reports/hooks/useReportsData';
import { fetchAdminVilleReports } from '../services/reportsService';
export function useReports() {
    const { data, isLoading } = useReportsData(fetchAdminVilleReports);
    const { user } = useAuth();
    const cities = user?.cities.map((city) => city.name) ?? [];
    const scopedData = useMemo(() => {
        if (!data) return null;
        if (cities.length > 0) return { ...data, scope: cities.join(' · ') };
        return {
            ...data,
            scope: 'Aucune ville attribuée',
            metrics: data.metrics.map((metric) => ({ ...metric, value: '—', helper: 'Aucun périmètre de ville disponible' })),
            trend: [], breakdown: [], attention: [],
        };
    }, [data, cities.join('|')]);
    return { data: scopedData, isLoading };
}
