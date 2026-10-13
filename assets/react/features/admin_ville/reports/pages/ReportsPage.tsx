import { useState } from 'react';

import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { Button } from '../../../../components/UI/Button';
import { Spinner } from '../../../../components/UI/Spinner';
import { EmptyState } from '../../../../components/Data/EmptyState';
import { Select } from '../../../../components/Forms/Select';
import { ReportPeriodFilter } from '../../../shared/reports/components/ReportPeriodFilter';
import { isValidPeriodRange } from '../../../shared/reports/periodPresets';
import { NoAssignedCityState } from '../../../shared/components/NoAssignedCityState';
import { canDo } from '../../../shared/permissions';
import { useReports } from '../hooks/useReports';
import { ReportsOverview } from '../components/ReportsOverview';
import { downloadAdminVilleReportPdf } from '../services/reportsService';
import '../../../../../styles/pages/admin_ville/reports/_reports.scss';

/**
 * Rapport ADMIN_VILLE — lecture seule.
 *
 * Le rapport est automatiquement rattaché à la première ville renvoyée par
 * le périmètre backend. Aucun sélecteur n'est nécessaire dans l'espace de
 * l'administrateur de ville ; l'API rejoue `checkCityAccess` à chaque requête.
 */
export function ReportsPage() {
    const { organizationRole } = useOrganization();
    const {
        data,
        isLoading,
        error,
        reload,
        cityUuid,
        setCityUuid,
        cities,
        periodFrom,
        setPeriodFrom,
        periodTo,
        setPeriodTo,
        citiesLoading,
        hasAssignedCities,
    } = useReports();

    const [isExporting, setExporting] = useState(false);
    const [exportError, setExportError] = useState<string | null>(null);
    const hasValidPeriod = isValidPeriodRange({ from: periodFrom, to: periodTo });

    if (citiesLoading) {
        return <div className="reports-loading" aria-busy="true"><Spinner size="large" /><span>Chargement de votre périmètre…</span></div>;
    }

    if (!hasAssignedCities) {
        return <main className="reports-page"><NoAssignedCityState /></main>;
    }

    const handleExportPdf = async () => {
        if (!cityUuid || isExporting || !hasValidPeriod) {
            if (!hasValidPeriod) setExportError('Choisissez une date de début et une date de fin valides avant de générer le PDF.');
            return;
        }
        setExporting(true);
        setExportError(null);
        try {
            await downloadAdminVilleReportPdf(cityUuid, {
                periodFrom: periodFrom || undefined,
                periodTo: periodTo || undefined,
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
        return <main className="reports-page"><EmptyState title="Aucun rapport" description="Aucune donnée d’occupation ou financière n’est disponible pour cette ville." action={<Button onClick={() => void reload()}>Réessayer</Button>} /></main>;
    }

    return (
        <main className="reports-page">
            <header className="reports-page__header">
                <div>
                    <span className="reports-page__eyebrow">{data.cityName}</span>
                    <h1>Rapports de la ville</h1>
                    <p>Occupation du patrimoine, impayés, dépenses et personnel de votre ville.</p>
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
                {cities.length > 1 && (
                    <div className="reports-page__city-filter">
                        <Select
                            aria-label="Choisir la ville"
                            value={cityUuid}
                            onChange={(event) => setCityUuid(event.target.value)}
                            options={cities.map((city) => ({ value: city.id, label: city.name }))}
                        />
                    </div>
                )}
                <div className="reports-page__toolbar-actions">
                    <span className="reports-page__period-label">Période affichée : {data.periodCovered.replace(' to ', ' → ')}</span>
                    {canDo(organizationRole, 'export_report') && (
                        <Button variant="outline" onClick={() => void handleExportPdf()} disabled={isExporting || !hasValidPeriod} aria-busy={isExporting}>
                            {isExporting ? 'Export…' : 'Exporter en PDF'}
                        </Button>
                    )}
                </div>
            </section>
            {!hasValidPeriod && <p className="reports-empty" role="alert">Choisissez une période complète pour générer le PDF.</p>}
            {exportError && <p className="reports-empty" role="alert">{exportError}</p>}
            <ReportsOverview data={data} />
        </main>
    );
}
