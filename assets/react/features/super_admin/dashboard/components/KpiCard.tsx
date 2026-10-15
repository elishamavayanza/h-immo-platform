import { Card } from '../../../../components/UI/Card';
import { DashboardIcon } from '../../icons/DashboardIcon';
import type { PlatformMetric } from '../types';

export interface KpiCardProps {
    title: string;
    icon: 'building' | 'users';
    metric: PlatformMetric;
}

export function KpiCard({ title, icon, metric }: KpiCardProps) {
    return (
        <Card className="sa-kpi" padding="medium">
            <div className="sa-kpi__head">
                <span className="sa-kpi__icon"><DashboardIcon name={icon} size={18} /></span>
                <span className="sa-kpi__label">{title}</span>
            </div>
            <p className="sa-kpi__value">{metric.total.toLocaleString('fr-FR')}</p>
            <ul className="sa-kpi__breakdown" aria-label={`Répartition ${title.toLocaleLowerCase('fr-FR')}`}>
                {metric.statuses.map((status) => (
                    <li key={status.label} className={`sa-kpi__status sa-kpi__status--${status.tone}`}>
                        <span>{status.label}</span>
                        <strong>{status.value.toLocaleString('fr-FR')}</strong>
                    </li>
                ))}
            </ul>
        </Card>
    );
}
