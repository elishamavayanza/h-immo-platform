import { Button } from '../../../../components/UI/Button';
import { Select } from '../../../../components/Forms/Select';
import { Spinner } from '../../../../components/UI/Spinner';
import { ReportsPageView } from '../../../shared/reports/components/ReportsPageView';
import type { ReportsData, ReportsPeriod } from '../../../shared/reports/types/report.types';
import { useAuth } from '../../../../app/providers/AuthProvider';

const DOWNLOAD_ICON = (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
        <polyline points="7 10 12 15 17 10" />
        <line x1="12" y1="15" x2="12" y2="3" />
    </svg>
);

export function ReportsOverview({
    data,
    isLoading,
    error,
    period,
    setPeriod,
    downloadPdf,
}: {
    data: ReportsData | null;
    isLoading: boolean;
    error: string | null;
    period: ReportsPeriod;
    setPeriod: (period: ReportsPeriod) => void;
    downloadPdf: () => void;
}) {
    const { accessToken } = useAuth();

    const handleDownloadPdf = async () => {
        await downloadPdf();
    };

    if (isLoading) return <div className="reports-loading"><Spinner size="large" /><span>Préparation du rapport…</span></div>;
    if (error || !data) {
        return (
            <main className="reports-page">
                <h1>Rapport indisponible</h1>
                <p>{error ?? 'Les données du rapport n\'ont pas pu être chargées.'}</p>
            </main>
        );
    }

    return (
        <main className="reports-page">
            <ReportsPageView data={data} isLoading={false} />
        </main>
    );
}