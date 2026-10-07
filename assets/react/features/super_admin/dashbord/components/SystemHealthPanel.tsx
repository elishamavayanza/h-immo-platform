import type { SystemHealthMetric } from '../types';
import { Badge } from '../../../../components/UI/Badge';

export interface SystemHealthPanelProps {
    metrics: SystemHealthMetric[];
}

const STATUS_LABEL = {
    healthy: 'Opérationnel',
    warning: 'À surveiller',
    critical: 'Critique',
} as const;

const STATUS_VARIANT = {
    healthy: 'success',
    warning: 'warning',
    critical: 'error',
} as const;

export function SystemHealthPanel({ metrics }: SystemHealthPanelProps) {
    return (
        <ul className="sa-health">
            {metrics.map((metric) => (
                <li key={metric.id} className={`sa-health__row sa-health__row--${metric.status}`}>
                    <span className="sa-health__label">{metric.label}</span>
                    <strong className="sa-health__value">{metric.value}{metric.unit}</strong>
                    <Badge variant={STATUS_VARIANT[metric.status]} size="small">
                        {STATUS_LABEL[metric.status]}
                    </Badge>
                </li>
            ))}
        </ul>
    );
}
