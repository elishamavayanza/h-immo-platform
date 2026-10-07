import { Card } from '../../../../components/UI/Card';
import type { ReportMetric } from '../types/report.types';

export function ReportMetricGrid({ metrics }: { metrics: ReportMetric[] }) {
    return (
        <section className="reports-metrics" aria-label="Indicateurs clés">
            {metrics.map((metric) => (
                <Card key={metric.id} className={`reports-metric reports-metric--${metric.tone}`} padding="medium">
                    <span className="reports-metric__label">{metric.label}</span>
                    <strong className="reports-metric__value">{metric.value}</strong>
                    <small>{metric.helper}</small>
                </Card>
            ))}
        </section>
    );
}
