import { Card } from '../../../../components/UI/Card';
import type { ReportsData, ReportsPeriod } from '../types/report.types';

const PERIOD_LABEL: Record<ReportsPeriod, string> = { month: '30 derniers jours', quarter: '3 derniers mois', year: '6 derniers mois' };
const POINT_COUNT: Record<ReportsPeriod, number> = { month: 1, quarter: 3, year: 6 };

export function ReportTrendCard({ data, period }: { data: ReportsData; period: ReportsPeriod }) {
    const points = data.trend.slice(-POINT_COUNT[period]);

    return (
        <Card className="reports-panel reports-trend" padding="medium">
            <div className="reports-panel__heading">
                <div><h2>Évolution sur la période</h2><p>{PERIOD_LABEL[period]} · montants en {data.currency}</p></div>
                <div className="reports-legend"><span><i className="reports-legend__revenue" /> Revenus</span><span><i className="reports-legend__expenses" /> Dépenses</span></div>
            </div>
            <div className="reports-chart" role="img" aria-label={`Évolution des revenus et dépenses, ${PERIOD_LABEL[period]}`}>
                {points.map((point) => (
                    <div className="reports-chart__group" key={point.label}>
                        <div className="reports-chart__bars">
                            <div className="reports-chart__bar reports-chart__bar--revenue" style={{ height: `${point.revenueShare}%` }} title={`Revenus : ${point.revenue} ${data.currency}`} />
                            <div className="reports-chart__bar reports-chart__bar--expenses" style={{ height: `${point.expenseShare}%` }} title={`Dépenses : ${point.expenses} ${data.currency}`} />
                        </div>
                        <span>{point.label}</span>
                    </div>
                ))}
            </div>
            <div className="reports-chart__footnote"><span>Dernier pointage : {points.at(-1)?.label ?? '—'}</span><span>Les valeurs sont une maquette locale.</span></div>
        </Card>
    );
}
