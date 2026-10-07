import { useAdminImmobilierDashboard } from '../hooks/useAdminImmobilierDashboard';
import { AdminImmobilierOverview } from '../components/AdminImmobilierOverview';
import { Spinner } from '../../../../components/UI/Spinner';
import '../../../../../styles/pages/admin_immobilier/dashboard/_dashboard.scss';
export function AdminImmobilierDashboardPage() { const data = useAdminImmobilierDashboard(); if (!data) return <div className="main-layout__page-loading" aria-busy="true"><Spinner size="large" className="spinner--page" /><span>Chargement des indicateurs…</span></div>; return <main className="organization-page"><header className="organization-page__header"><div><span className="organization-page__eyebrow">ESPACE ADMIN IMMOBILIER</span><h1>Tableau de bord</h1><p>Pilotez les locations, les paiements et les opérations de votre organisation.</p></div></header><AdminImmobilierOverview data={data} /></main>; }
