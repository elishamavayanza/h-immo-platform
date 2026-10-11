import { useState } from 'react';

import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { Button } from '../../../../components/UI/Button';
import { Spinner } from '../../../../components/UI/Spinner';
import { EmptyState } from '../../../../components/Data/EmptyState';
import { DateRangePicker } from '../../../../components/Forms/DateRangePicker';
import { CitySelect } from '../../../shared/components/CitySelect';
import { NoAssignedCityState } from '../../../shared/components/NoAssignedCityState';
import { canDo } from '../../../shared/permissions';
import { useReports } from '../hooks/useReports';
import { ReportsOverview } from '../components/ReportsOverview';
import { downloadAdminVilleReportPdf } from '../services/reportsService';
import '../../../../../styles/pages/admin_ville/reports/_reports.scss';

/**
 * Rapport ADMIN_VILLE — lecture seule.
 *
 * La ville est choisie parmi les villes réellement affectées (`CitySelect`) ;
 * le backend rejoue `checkCityAccess` + `VIEW_REPORT` à chaque requête. Aucune
 * écriture n'est proposée depuis cette page ; seul l'export PDF (endpoint
 * `format=pdf`) est exposé, et uniquement si le rôle y a droit.
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
        periodFrom,
        setPeriodFrom,
        periodTo,
        setPeriodTo,
        citiesLoading,
        hasAssignedCities,
    } = useReports();

    const [isExporting, setExporting] = useState(false);
    const [exportError, setExportError] = useState<string | null>(null);

    if (citiesLoading) {
        return <div className="reports-loading" aria-busy="true"><Spinner size="large" /><span>Chargement de votre périmètre…</span></div>;
    }

    if (!hasAssignedCities) {
        return <main className="reports-page"><NoAssignedCityState /></main>;
    }

    const handleExportPdf = async () => {
        if (!cityUuid || isExporting) return;
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
                <div className="reports-page__controls">
                    <CitySelect id="reports-city" value={cityUuid} onChange={setCityUuid} />
                    <DateRangePicker
                        startDate={periodFrom}
                        endDate={periodTo}
                        onChange={(range) => { setPeriodFrom(range.startDate); setPeriodTo(range.endDate); }}
                    />
                    <span className="reports-page__period-label">Période : {data.periodCovered.replace(' to ', ' → ')}</span>
                    {canDo(organizationRole, 'export_report') && (
                        <Button variant="outline" onClick={() => void handleExportPdf()} disabled={isExporting}>
                            {isExporting ? 'Export…' : 'Exporter en PDF'}
                        </Button>
                    )}
                </div>
            </header>
            {exportError && <p className="reports-empty" role="alert">{exportError}</p>}
            <ReportsOverview data={data} />
        </main>
    );
}