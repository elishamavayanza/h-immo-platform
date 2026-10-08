import { useState } from 'react';
import { Select } from '../../../../components/Forms/Select';
import { Spinner } from '../../../../components/UI/Spinner';
import type { ReportsData, ReportsPeriod } from '../types/report.types';
import { ReportAttentionCard } from './ReportAttentionCard';
import { ReportBreakdownCard } from './ReportBreakdownCard';
import { ReportMetricGrid } from './ReportMetricGrid';
import { ReportTrendCard } from './ReportTrendCard';

export function ReportsPageView({
    data,
    isLoading = false,
    showHeader = true,
    period: controlledPeriod,
    setPeriod: setControlledPeriod,
}: {
    data: ReportsData | null;
    isLoading?: boolean;
    showHeader?: boolean;
    period?: ReportsPeriod;
    setPeriod?: (period: ReportsPeriod) => void;
}) {
    const [localPeriod, setLocalPeriod] = useState<ReportsPeriod>('year');
    const period = controlledPeriod ?? localPeriod;
    const setPeriod = setControlledPeriod ?? setLocalPeriod;
    if (isLoading) return <div className="reports-loading"><Spinner size="large" /><span>Préparation du rapport…</span></div>;
    if (!data) return <main className="reports-page"><h1>Rapport indisponible</h1><p>Les données du rapport n’ont pas pu être chargées.</p></main>;

    return (
        <div className={showHeader ? 'reports-page' : 'reports-page__content'}>
            {showHeader && (
                <header className="reports-page__header">
                    <div><span className="reports-page__eyebrow">{data.scope}</span><h1>{data.title}</h1><p>{data.description}</p></div>
                    <div className="reports-page__controls">
                        <span className="reports-page__mock-label">Maquette locale · données illustratives</span>
                        <Select aria-label="Période du rapport" value={period} onChange={(event) => setPeriod(event.target.value as ReportsPeriod)} options={[{ value: 'month', label: '30 derniers jours' }, { value: 'quarter', label: '3 derniers mois' }, { value: 'year', label: '6 derniers mois' }]} />
                    </div>
                </header>
            )}
            <ReportMetricGrid metrics={data.metrics} />
            <ReportTrendCard data={data} period={period} />
            <section className="reports-lower-grid">
                <ReportBreakdownCard title={data.breakdownTitle} items={data.breakdown} />
                <ReportAttentionCard title={data.attentionTitle} items={data.attention} />
            </section>
        </div>
    );
}
