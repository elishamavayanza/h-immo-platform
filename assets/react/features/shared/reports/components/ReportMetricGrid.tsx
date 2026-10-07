import { InlineSummary } from '../../components/InlineSummary';
import type { ReportMetric } from '../types/report.types';

export function ReportMetricGrid({ metrics }: { metrics: ReportMetric[] }) {
    return <InlineSummary items={metrics.map((metric) => ({ label: metric.label, value: metric.value, detail: metric.helper }))} />;
}
