// ============================================================
// Page : Tableau de bord SUPER_ADMIN.
// Route  : /app/admin/dashboard (cf. PLATFORM_SIDEBAR).
//
// Vue plateforme : compteurs globaux et activité d'administration,
// sans indicateurs métier détaillés des organisations.
// ============================================================

import type { ReactNode } from 'react';

import '../../../../../styles/pages/super_admin/_dashboard.scss';
import { EmptyState } from '../../../../components/Data/EmptyState';
import { Button } from '../../../../components/UI/Button';
import { Card } from '../../../../components/UI/Card';
import { Spinner } from '../../../../components/UI/Spinner';

import {
    ActivityFeed,
    DashboardHeader,
    KpiGrid,
} from '../components';
import { useSuperAdminDashboard } from '../hooks/useSuperAdminDashboard';

interface DashboardPanelProps {
    title: string;
    description: string;
    children: ReactNode;
}

function DashboardPanel({ title, description, children }: DashboardPanelProps) {
    return (
        <Card
            className="sa-dashboard-card"
            padding="medium"
            header={
                <div className="sa-card__head">
                    <div>
                        <h2 className="sa-card__title">{title}</h2>
                        <p className="sa-card__sub">{description}</p>
                    </div>
                </div>
            }
        >
            {children}
        </Card>
    );
}

export function SuperAdminDashboardPage() {
    const { data, isLoading, error, reload } = useSuperAdminDashboard();

    if (isLoading && !data) return <div className="main-layout__page-loading" aria-busy="true"><Spinner size="large" className="spinner--page" /><span>Chargement du tableau de bord…</span></div>;

    if (error || !data) {
        return (
            <EmptyState
                title="Impossible de charger le tableau de bord"
                description="Réessayez dans un instant. Si le problème persiste, contactez l’équipe plateforme."
                action={<Button onClick={reload}>Réessayer</Button>}
            />
        );
    }

    return (
        <div className="sa-dashboard">
            <DashboardHeader onReload={reload} isRefreshing={isLoading} generatedAt={data.generatedAt} />

            <KpiGrid organizations={data.organizations} users={data.users} />

            <DashboardPanel
                title="Activité d’administration"
                description="Créations, suspensions et réactivations des comptes et organisations."
            >
                <ActivityFeed entries={data.activity} />
            </DashboardPanel>
        </div>
    );
}
