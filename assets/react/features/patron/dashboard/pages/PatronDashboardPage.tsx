import { EmptyState } from '../../../../components/Data/EmptyState';
import { Button } from '../../../../components/UI/Button';
import { Spinner } from '../../../../components/UI/Spinner';
import { usePatronDashboard } from '../hooks/usePatronDashboard';
import { PatronOverview } from '../components/PatronOverview';
import '../../../../../styles/pages/patron/dashboard/_dashboard.scss';

export function PatronDashboardPage() {
    const { data, isLoading } = usePatronDashboard();
    if (isLoading) return <div className="main-layout__page-loading" aria-busy="true"><Spinner size="large" className="spinner--page" /><span>Chargement du tableau de bord…</span></div>;
    if (!data) return <EmptyState title="Tableau de bord indisponible" description="Les données de démonstration n’ont pas pu être chargées." action={<Button onClick={() => window.location.reload()}>Réessayer</Button>} />;
    return <main className="organization-page"><header className="organization-page__header"><div><span className="organization-page__eyebrow">ESPACE PATRON</span><h1>Tableau de bord</h1><p>Vue d’ensemble de votre patrimoine et de votre activité locative.</p></div></header><PatronOverview data={data} /></main>;
}
