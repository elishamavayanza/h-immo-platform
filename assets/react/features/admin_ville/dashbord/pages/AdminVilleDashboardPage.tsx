import { useNavigate } from 'react-router-dom';

import { Spinner } from '../../../../components/UI/Spinner';
import { EmptyState } from '../../../../components/Data/EmptyState';
import { Button } from '../../../../components/UI/Button';
import { Select } from '../../../../components/Forms/Select';
import { NoAssignedCityState } from '../../../shared/components/NoAssignedCityState';
import { useAdminVilleDashboard } from '../hooks/useAdminVilleDashboard';
import { AdminVilleOverview } from '../components/AdminVilleOverview';
import '../../../../../styles/pages/admin_ville/dashboard/_dashboard.scss';

/**
 * Tableau de bord ADMIN_VILLE — lecture seule.
 *
 * Les indicateurs proviennent du rapport réel de la ville sélectionnée ; la
 * page ne propose aucune écriture, uniquement des raccourcis de navigation
 * vers les écrans d'action correspondants.
 */
export function AdminVilleDashboardPage() {
    const navigate = useNavigate();
    const { data, isLoading, error, reload, cities, cityUuid, setCityUuid, citiesLoading, hasAssignedCities } = useAdminVilleDashboard();

    if (citiesLoading) {
        return <div className="main-layout__page-loading" aria-busy="true"><Spinner size="large" className="spinner--page" /><span>Chargement de votre périmètre…</span></div>;
    }

    if (!hasAssignedCities) {
        return <main className="organization-page"><NoAssignedCityState /></main>;
    }

    if (isLoading) {
        return <div className="main-layout__page-loading" aria-busy="true"><Spinner size="large" className="spinner--page" /><span>Chargement des indicateurs…</span></div>;
    }

    if (error) {
        return <main className="organization-page"><EmptyState title="Tableau de bord indisponible" description={error} action={<Button onClick={() => void reload()}>Réessayer</Button>} /></main>;
    }

    if (!data) {
        return <main className="organization-page"><EmptyState title="Aucune donnée" description="Aucun indicateur n’est disponible pour cette ville." action={<Button onClick={() => void reload()}>Réessayer</Button>} /></main>;
    }

    return (
        <main className="organization-page">
            <header className="organization-page__header">
                <div>
                    <span className="organization-page__eyebrow">ESPACE ADMIN VILLE</span>
                    <h1>Tableau de bord</h1>
                    <p>Suivez l’activité immobilière et locative de {data.cityName}.</p>
                </div>
            </header>

            <div className="organization-dashboard-toolbar">
                {cities.length > 1 && (
                    <div className="organization-dashboard-toolbar__city">
                        <Select fullWidth aria-label="Ville active" value={cityUuid} onChange={(event) => setCityUuid(event.target.value)} options={cities.map((city) => ({ value: city.id, label: city.name }))} />
                    </div>
                )}
                <nav className="organization-quick-actions" aria-label="Actions principales">
                    <Button variant="outline" onClick={() => navigate('/app/location/loyers')}>Consulter les impayés</Button>
                    <Button variant="outline" onClick={() => navigate('/app/patrimoine')}>Gérer le patrimoine</Button>
                </nav>
            </div>

            <AdminVilleOverview data={data} />
        </main>
    );
}
