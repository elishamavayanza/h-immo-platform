import { useAdminImmobilierDashboard } from '../hooks/useAdminImmobilierDashboard';
import { AdminImmobilierOverview } from '../components/AdminImmobilierOverview';
import '../../../../../styles/pages/admin_immobilier/dashboard/_dashboard.scss';
export function AdminImmobilierDashboardPage() { const data = useAdminImmobilierDashboard(); return <main className="patron-page"><header className="patron-page__header"><div><span className="patron-page__eyebrow">ESPACE ADMIN IMMOBILIER</span><h1>Tableau de bord</h1><p>Pilotez les locations, les paiements et les opérations de votre organisation.</p></div></header>{data ? <AdminImmobilierOverview data={data} /> : <p>Chargement des indicateurs…</p>}</main>; }
