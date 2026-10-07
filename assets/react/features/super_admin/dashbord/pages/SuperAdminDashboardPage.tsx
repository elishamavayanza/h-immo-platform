// ============================================================
// Page : Tableau de bord SUPER_ADMIN.
// Route  : /app/admin/dashboard (cf. PLATFORM_SIDEBAR).
//
// Vue plateforme : aucune donnée d'organisation métier, uniquement
// l'activité globale (organisations, utilisateurs, revenus, santé).
// Données mockées — l'appel API viendra plus tard.
// ============================================================

import type { ReactNode } from 'react';

import '../../../../../styles/pages/super_admin/_dashboard.scss';
import { EmptyState } from '../../../../components/Data/EmptyState';
import { Button } from '../../../../components/UI/Button';
import { Card } from '../../../../components/UI/Card';

import {
    ActivityFeed,
    DashboardHeader,
    DashboardSkeleton,
    KpiGrid,
    OrganizationsTable,
    RevenueChart,
    SystemHealthPanel,
    TopCitiesPanel,
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

    if (isLoading && !data) return <DashboardSkeleton />;

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
            <DashboardHeader onReload={reload} isRefreshing={isLoading} />

            <KpiGrid metrics={data.kpis} />

            <section className="sa-grid sa-grid--2-1">
                <DashboardPanel
                    title="Croissance"
                    description="Revenu mensuel récurrent et abonnements sur 12 mois."
                >
                    <RevenueChart series={data.revenueSeries} />
                </DashboardPanel>

                <DashboardPanel title="Santé système" description="Infrastructure & API">
                    <SystemHealthPanel metrics={data.health} />
                </DashboardPanel>
            </section>

            <section className="sa-grid sa-grid--2-1">
                <DashboardPanel
                    title="Organisations récentes"
                    description="Dernières inscriptions sur la plateforme."
                >
                    <OrganizationsTable organizations={data.recentOrganizations} />
                </DashboardPanel>

                <DashboardPanel title="Activité" description="Journal temps réel">
                    <ActivityFeed entries={data.activity} />
                </DashboardPanel>
            </section>

            <section className="sa-grid sa-grid--1">
                <DashboardPanel
                    title="Top villes"
                    description="Répartition géographique des propriétés publiées."
                >
                    <TopCitiesPanel cities={data.topCities} />
                </DashboardPanel>
            </section>
        </div>
    );
}
