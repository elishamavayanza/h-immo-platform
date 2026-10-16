import { useCallback, useEffect, useRef, useState } from 'react';

import { fetchSuperAdminReport, downloadSuperAdminReportPdf, mapToReportsData } from '../services/reportsService';
import type { ReportsData } from '../../../shared/reports/types/report.types';
import { computePeriodPreset, isValidPeriodRange, type PeriodRange } from '../../../shared/reports/utils/periodPresets';

export function useReports() {
    const [data, setData] = useState<ReportsData | null>(null);
    const [isLoading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [period, setPeriod] = useState<PeriodRange>(() => computePeriodPreset('this_year'));
    const [isExporting, setExporting] = useState(false);
    const requestVersion = useRef(0);

    const reload = useCallback(async () => {
        const version = ++requestVersion.current;
        setLoading(true);
        setError(null);
        try {
            const response = await fetchSuperAdminReport({
                periodFrom: period.from,
                periodTo: period.to,
            });
            const mapped = mapToReportsData(response);
            if (version === requestVersion.current) setData(mapped);
        } catch (err) {
            if (version === requestVersion.current) setError(err instanceof Error ? err.message : 'Impossible de charger le rapport');
        } finally {
            if (version === requestVersion.current) setLoading(false);
        }
    }, [period.from, period.to]);

    useEffect(() => {
        void reload();
    }, [reload]);

    const downloadPdf = useCallback(async () => {
        if (isExporting || !isValidPeriodRange(period)) {
            setError('Choisissez une période complète et valide avant de générer le PDF.');
            return;
        }

        setExporting(true);
        setError(null);
        try {
            await downloadSuperAdminReportPdf({
                periodFrom: period.from,
                periodTo: period.to,
            });
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Échec du téléchargement PDF');
        } finally {
            setExporting(false);
        }
    }, [isExporting, period]);

    return { data, isLoading, error, reload, period, setPeriod, downloadPdf, isExporting };
}
