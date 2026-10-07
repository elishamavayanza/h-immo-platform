import { KpiCard } from './KpiCard';
import type { KpiMetric } from '../types';

export interface KpiGridProps {
    metrics: KpiMetric[];
}

export function KpiGrid({ metrics }: KpiGridProps) {
    return (
        <section className="sa-kpi-grid" aria-label="Indicateurs clés">
            {metrics.map((m) => (
                <KpiCard key={m.id} metric={m} />
            ))}
        </section>
    );
}
