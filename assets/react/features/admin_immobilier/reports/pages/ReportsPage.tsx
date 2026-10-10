import { Button } from '../../../../components/UI/Button';
import { EmptyState } from '../../../../components/Data/EmptyState';
import { Spinner } from '../../../../components/UI/Spinner';
import { useReports } from '../hooks/useReports';
import { ReportsOverview } from '../components/ReportsOverview';
import '../../../../../styles/pages/admin_immobilier/reports/_reports.scss';

export function ReportsPage() {
    const { data, isLoading, error, reload } = useReports();

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
                <div className="reports-page__controls">
                    <span className="reports-page__period-label">Période : {data.periodCovered.replace(' to ', ' → ')}</span>
                </div>
            </header>
            <ReportsOverview data={data} />
        </main>
    );
}