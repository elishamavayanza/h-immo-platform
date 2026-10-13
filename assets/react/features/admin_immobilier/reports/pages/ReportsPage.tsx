import { useState } from 'react';

import { Button } from '../../../../components/UI/Button';
import { EmptyState } from '../../../../components/Data/EmptyState';
import { Spinner } from '../../../../components/UI/Spinner';
import { Select } from '../../../../components/Forms/Select';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { ReportPeriodFilter } from '../../../shared/reports/components/ReportPeriodFilter';
import { useReports } from '../hooks/useReports';
import { ReportsOverview } from '../components/ReportsOverview';
import { downloadAdminImmobilierReportPdf } from '../services/reportsService';
import { canDo } from '../../../shared/permissions';
import '../../../../../styles/pages/admin_immobilier/reports/_reports.scss';

export function ReportsPage() {
    const { organizationRole, currentOrganization } = useOrganization();
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
        if (!organizationUuid || isExporting) return;
        setExporting(true);
        setExportError(null);
        try {
            await downloadAdminImmobilierReportPdf(organizationUuid, {
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
                    <h1>Rapports immobiliers</h1>
                    <p>Occupation du patrimoine, impayés et dépenses liées aux biens.</p>
                </div>
            </header>
            <section className="reports-page__toolbar" aria-label="Filtres du rapport">
                <div className="reports-page__date-filter">
                    <ReportPeriodFilter
                        periodFrom={periodFrom}
                        periodTo={periodTo}
                        onChange={(range) => { setPeriodFrom(range.from); setPeriodTo(range.to); }}
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
                    {canDo(organizationRole, 'export_report') && (
                        <Button variant="outline" onClick={() => void handleExportPdf()} disabled={isExporting} aria-busy={isExporting}>
                            {isExporting ? 'Export…' : 'Exporter en PDF'}
                        </Button>
                    )}
                </div>
            </section>
            {exportError && <p className="reports-empty" role="alert">{exportError}</p>}
            <ReportsOverview data={data} />
        </main>
    );
}
