import { useAdminImmobilierDashboard } from '../hooks/useAdminImmobilierDashboard';
import { AdminImmobilierOverview } from '../components/AdminImmobilierOverview';
import { Spinner } from '../../../../components/UI/Spinner';
import { EmptyState } from '../../../../components/Data/EmptyState';
import { Button } from '../../../../components/UI/Button';
import '../../../../../styles/pages/admin_immobilier/dashboard/_dashboard.scss';

export function AdminImmobilierDashboardPage() {
    const { data, isLoading, error, reload } = useAdminImmobilierDashboard();

    if (isLoading) {
        return <div className="main-layout__page-loading" aria-busy="true"><Spinner size="large" className="spinner--page" /><span>Chargement des indicateurs…</span></div>;
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
                    <span className="organization-page__eyebrow">ESPACE ADMIN IMMOBILIER</span>
                    <h1>Tableau de bord</h1>
                    <p>Pilotez les locations, les paiements et les opérations de {data.organizationName}.</p>
                </div>
            </header>
            <AdminImmobilierOverview data={data} />
        </main>
    );
}