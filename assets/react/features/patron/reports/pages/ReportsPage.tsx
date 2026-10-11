import { useState } from 'react';

import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { Button } from '../../../../components/UI/Button';
import { Spinner } from '../../../../components/UI/Spinner';
import { EmptyState } from '../../../../components/Data/EmptyState';
import { DateRangePicker } from '../../../../components/Forms/DateRangePicker';
import { Select } from '../../../../components/Forms/Select';
import { useReports } from '../hooks/useReports';
import { ReportsOverview } from '../components/ReportsOverview';
import { downloadPatronReportPdf } from '../services/reportsService';
import '../../../../../styles/pages/patron/reports/_reports.scss';

/**
 * Rapport PATRON — lecture seule.
 *
 * Le rapport est borné côté backend à l'organisation active (et, si fourni, à
 * la ville choisie). Le PATRON peut filtrer par période et par ville et
 * exporter le PDF ; l'API rejoue le contrôle d'accès à chaque requête.
 */
export function ReportsPage() {
    const { currentOrganization } = useOrganization();
    const organizationUuid = currentOrganization?.uuid ?? '';
    const {
        data,
        isLoading,
        error,
        reload,
        cities,
        cityUuid,
        setCityUuid,
        periodFrom,
        setPeriodFrom,
        periodTo,
        setPeriodTo,
    } = useReports();

    const [isExporting, setExporting] = useState(false);
    const [exportError, setExportError] = useState<string | null>(null);

    const handleExportPdf = async () => {
        if (!data || isExporting || !organizationUuid) return;
        setExporting(true);
        setExportError(null);
        try {
            await downloadPatronReportPdf(organizationUuid, {
                periodFrom: periodFrom || undefined,
                periodTo: periodTo || undefined,
                cityUuid: cityUuid || undefined,
            });
        } catch (cause) {
            setExportError(cause instanceof Error ? cause.message : 'Export impossible pour le moment.');
        } finally {
            setExporting(false);
        }
    };

    if (isLoading) {
        return <div className="reports-loading" aria-busy="true"><Spinner size="large" /><span>Préparation du rapport…</span></div>;
    }

    if (error) {
        return <main className="reports-page"><EmptyState title="Rapport indisponible" description={error} action={<Button onClick={() => void reload()}>Réessayer</Button>} /></main>;
    }

    if (!data) {
        return <main className="reports-page"><EmptyState title="Aucun rapport" description="Aucune donnée d’occupation ou financière n’est disponible pour votre organisation." action={<Button onClick={() => void reload()}>Réessayer</Button>} /></main>;
    }

    return (
        <main className="reports-page">
            <header className="reports-page__header">
                <div>
                    <span className="reports-page__eyebrow">{data.organizationName}</span>
                    <h1>Rapports</h1>
                    <p>Finances, occupation, impayés et dépenses de votre organisation.</p>
                </div>
            </header>
            <section className="reports-page__toolbar" aria-label="Filtres du rapport">
                <div className="reports-page__date-filter">
                    <DateRangePicker
                        startDate={periodFrom}
                        endDate={periodTo}
                        fullWidth
                        onChange={(range) => { setPeriodFrom(range.startDate); setPeriodTo(range.endDate); }}
                    />
                </div>
                <div className="reports-page__city-filter">
                    <Select
                        aria-label="Filtrer par ville"
                        value={cityUuid}
                        onChange={(event) => setCityUuid(event.target.value)}
                        options={[{ value: '', label: 'Toutes les villes' }, ...cities]}
                    />
                </div>
                <div className="reports-page__toolbar-actions">
                    <span className="reports-page__period-label">Période affichée : {data.periodCovered.replace(' to ', ' → ')}</span>
                    <Button variant="outline" onClick={() => void handleExportPdf()} disabled={isExporting} aria-busy={isExporting}>
                        {isExporting ? 'Export…' : 'Exporter en PDF'}
                    </Button>
                </div>
            </section>
            {exportError && <p className="reports-empty" role="alert">{exportError}</p>}
            <ReportsOverview data={data} />
        </main>
    );
}
