import type { PlatformMetric } from '../types';
import { KpiCard } from './KpiCard';

export interface KpiGridProps {
    organizations: PlatformMetric;
    users: PlatformMetric;
}

export function KpiGrid({ organizations, users }: KpiGridProps) {
    return (
        <section className="sa-kpi-grid" aria-label="Indicateurs de la plateforme">
            <KpiCard title="Organisations" icon="building" metric={organizations} />
            <KpiCard title="Comptes utilisateurs" icon="users" metric={users} />
        </section>
    );
}
