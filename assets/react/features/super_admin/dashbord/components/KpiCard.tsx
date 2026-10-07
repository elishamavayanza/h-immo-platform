import { DashboardIcon } from './DashboardIcon';
import { Card } from '../../../../components/UI/Card';
import type { KpiMetric } from '../types';

const ARROW: Record<KpiMetric['trend'], 'arrow-up' | 'arrow-down' | 'minus'> = {
    up: 'arrow-up',
    down: 'arrow-down',
    flat: 'minus',
};

export interface KpiCardProps {
    metric: KpiMetric;
}

export function KpiCard({ metric }: KpiCardProps) {
    const sign = metric.delta > 0 ? '+' : '';
    const polarity = metric.positive ? 'is-good' : 'is-bad';

    return (
        <Card className={`sa-kpi sa-kpi--${metric.tone}`} padding="medium">
            <div className="sa-kpi__head">
                <span className="sa-kpi__icon">
                    <DashboardIcon name={metric.icon} size={18} />
                </span>
                <span className="sa-kpi__label">{metric.label}</span>
            </div>
            <p className="sa-kpi__value">{metric.value}</p>
            <div className="sa-kpi__foot">
                <span className={`sa-kpi__trend ${polarity}`}>
                    <DashboardIcon name={ARROW[metric.trend]} size={12} />
                    {sign}{metric.delta.toLocaleString('fr-FR', { maximumFractionDigits: 1 })}&nbsp;%
                </span>
                <span className="sa-kpi__helper">{metric.helper}</span>
            </div>
        </Card>
    );
}
