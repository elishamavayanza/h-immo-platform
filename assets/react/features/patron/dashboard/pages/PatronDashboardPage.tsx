import { EmptyState } from '../../../../components/Data/EmptyState';
import { Button } from '../../../../components/UI/Button';
import { Spinner } from '../../../../components/UI/Spinner';
import { usePatronDashboard } from '../hooks/usePatronDashboard';
import { PatronOverview } from '../components/PatronOverview';
import '../../../../../styles/pages/patron/dashboard/_dashboard.scss';

/**
 * Tableau de bord PATRON.
 *
 * Projette le rapport `/v1/reports/patron` de l'organisation active en
 * indicateurs : finances, occupation, impayés et dépenses. Aucun mock : toutes
 * les valeurs proviennent du backend, bornées à l'organisation de l'appelant.
 */
export function PatronDashboardPage() {
    const { data, isLoading, error, reload } = usePatronDashboard();

    if (isLoading) {
        return <div className="main-layout__page-loading" aria-busy="true"><Spinner size="large" className="spinner--page" /><span>Chargement du tableau de bord…</span></div>;
    }

    if (error) {
        return <main className="organization-page"><EmptyState title="Tableau de bord indisponible" description={error} action={<Button onClick={() => void reload()}>Réessayer</Button>} /></main>;
    }

    if (!data) {
        return <main className="organization-page"><EmptyState title="Aucune donnée" description="Aucun indicateur n’est disponible pour votre organisation." action={<Button onClick={() => void reload()}>Réessayer</Button>} /></main>;
    }

    return (
        <main className="organization-page">
            <header className="organization-page__header">
                <div>
                    <span className="organization-page__eyebrow">ESPACE PATRON</span>
                    <h1>Tableau de bord</h1>
                    <p>Vue d’ensemble de votre patrimoine et de votre activité locative.</p>
                </div>
            </header>
            <PatronOverview data={data} />
        </main>
    );
}
