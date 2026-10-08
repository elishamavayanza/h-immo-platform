import { useCallback, useEffect, useState } from 'react';

import { fetchSuperAdminReport, downloadSuperAdminReportPdf, mapToReportsData } from '../services/reportsService';
import type { ReportsData } from '../../../shared/reports/types/report.types';
import type { ReportsPeriod } from '../../../shared/reports/types/report.types';

export function useReports() {
    const [data, setData] = useState<ReportsData | null>(null);
    const [isLoading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [period, setPeriod] = useState<ReportsPeriod>('year');

    const reload = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const response = await fetchSuperAdminReport({
                periodFrom: period === 'month' ? getStartOfMonth(-1) : period === 'quarter' ? getStartOfMonth(-3) : getStartOfYear(),
                periodTo: getEndOfToday(),
            });
            const mapped = mapToReportsData(response);
            setData(mapped);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Impossible de charger le rapport');
        } finally {
            setLoading(false);
        }
    }, [period]);

    useEffect(() => {
        void reload();
    }, [reload]);

    const downloadPdf = useCallback(async () => {
        try {
            await downloadSuperAdminReportPdf({
                periodFrom: period === 'month' ? getStartOfMonth(-1) : period === 'quarter' ? getStartOfMonth(-3) : getStartOfYear(),
                periodTo: getEndOfToday(),
            });
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Échec du téléchargement PDF');
        }
    }, [period]);

    return { data, isLoading, error, reload, period, setPeriod, downloadPdf };
}

function getStartOfYear(): string {
    return new Date(new Date().getFullYear(), 0, 1).toISOString().split('T')[0];
}

function getStartOfMonth(monthsAgo: number): string {
    const date = new Date();
    date.setMonth(date.getMonth() + monthsAgo);
    date.setDate(1);
    return date.toISOString().split('T')[0];
}

function getEndOfToday(): string {
    return new Date().toISOString().split('T')[0];
}