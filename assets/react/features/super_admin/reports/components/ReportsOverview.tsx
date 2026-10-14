import { Button } from '../../../../components/UI/Button';
import { Spinner } from '../../../../components/UI/Spinner';
import { ReportPeriodFilter } from '../../../shared/reports/components/ReportPeriodFilter';
import { isValidPeriodRange, type PeriodRange } from '../../../shared/reports/utils/periodPresets';
import { ReportsPageView } from '../../../shared/reports/components/ReportsPageView';
import type { ReportsData } from '../../../shared/reports/types/report.types';

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
    isExporting,
}: {
    data: ReportsData | null;
    isLoading: boolean;
    error: string | null;
    period: PeriodRange;
    setPeriod: (period: PeriodRange) => void;
    downloadPdf: () => void;
    isExporting: boolean;
}) {
    const hasValidPeriod = isValidPeriodRange(period);

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
            <header className="reports-page__header">
                <div>
                    <span className="reports-page__eyebrow">{data.scope}</span>
                    <h1>{data.title}</h1>
                    <p>{data.description}</p>
                </div>
            </header>
            <section className="reports-page__toolbar" aria-label="Filtres du rapport">
                <div className="reports-page__date-filter">
                    <ReportPeriodFilter periodFrom={period.from} periodTo={period.to} onChange={setPeriod} disabled={isExporting} />
                </div>
                <div className="reports-page__toolbar-actions">
                    <span className="reports-page__period-label">Période : {data.periodLabel.replace(' to ', ' → ')}</span>
                    <Button variant="outline" onClick={downloadPdf} disabled={isExporting || !hasValidPeriod} aria-busy={isExporting} icon={DOWNLOAD_ICON} iconPosition="left">
                        {isExporting ? 'Préparation…' : 'Télécharger PDF'}
                    </Button>
                </div>
            </section>
            {!hasValidPeriod && <p className="reports-empty" role="alert">Choisissez une période complète pour générer le PDF.</p>}
            <ReportsPageView data={data} isLoading={false} showHeader={false} />
        </main>
    );
}
