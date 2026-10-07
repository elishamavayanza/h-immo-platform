import { useAdminVilleDashboard } from '../hooks/useAdminVilleDashboard';
import { AdminVilleOverview } from '../components/AdminVilleOverview';
import '../../../../../styles/pages/admin_ville/dashboard/_dashboard.scss';
export function AdminVilleDashboardPage() { const data = useAdminVilleDashboard(); return <main className="patron-page"><header className="patron-page__header"><div><span className="patron-page__eyebrow">ESPACE ADMIN VILLE · {data.city}</span><h1>Tableau de bord</h1><p>Suivez l’activité immobilière et locative dans votre périmètre.</p></div></header><AdminVilleOverview data={data} /></main>; }
