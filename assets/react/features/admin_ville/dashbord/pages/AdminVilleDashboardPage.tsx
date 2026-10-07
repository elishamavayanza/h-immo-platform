import { useAdminVilleDashboard } from '../hooks/useAdminVilleDashboard';
import { AdminVilleOverview } from '../components/AdminVilleOverview';
import { Spinner } from '../../../../components/UI/Spinner';
import '../../../../../styles/pages/admin_ville/dashboard/_dashboard.scss';
export function AdminVilleDashboardPage() { const { data, isLoading } = useAdminVilleDashboard(); if (isLoading || !data) return <div className="main-layout__page-loading" aria-busy="true"><Spinner size="large" className="spinner--page" /><span>Chargement de votre ville…</span></div>; return <main className="organization-page"><header className="organization-page__header"><div><span className="organization-page__eyebrow">ESPACE ADMIN VILLE · {data.city}</span><h1>Tableau de bord</h1><p>Suivez l’activité immobilière et locative dans votre périmètre.</p></div></header><AdminVilleOverview data={data} /></main>; }
