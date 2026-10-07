import { EmptyState } from '../../../../components/Data/EmptyState';
import { Button } from '../../../../components/UI/Button';
import { usePatronDashboard } from '../hooks/usePatronDashboard';
import { PatronOverview } from '../components/PatronOverview';
import '../../../../../styles/pages/patron/dashboard/_dashboard.scss';

export function PatronDashboardPage() {
    const { data, isLoading } = usePatronDashboard();
    if (isLoading) return <div className="patron-page"><p>Chargement du tableau de bord…</p></div>;
    if (!data) return <EmptyState title="Tableau de bord indisponible" description="Les données de démonstration n’ont pas pu être chargées." action={<Button onClick={() => window.location.reload()}>Réessayer</Button>} />;
    return <main className="patron-page"><header className="patron-page__header"><div><span className="patron-page__eyebrow">ESPACE PATRON</span><h1>Tableau de bord</h1><p>Vue d’ensemble de votre patrimoine et de votre activité locative.</p></div></header><PatronOverview data={data} /></main>;
}
