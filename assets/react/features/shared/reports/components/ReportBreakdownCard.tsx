import { Card } from '../../../../components/UI/Card';
import type { ReportBreakdownItem } from '../types/report.types';
import { DonutChart } from '../../../../components/Data/DonutChart';
import { ChartDisplayToggle } from '../../../../components/Data/ChartDisplayToggle';

export function ReportBreakdownCard({ title, items }: { title: string; items: ReportBreakdownItem[] }) {
    return (
        <Card className="reports-panel reports-breakdown reports-breakdown--occupancy" padding="medium">
            <div className="reports-panel__heading"><div><h2>{title}</h2><p>Répartition des indicateurs sur le périmètre sélectionné.</p></div><ChartDisplayToggle /></div>
            <div className="reports-breakdown__list" role="region" aria-label={`${title} : liste déroulante`} tabIndex={0}>
                {items.map((item) => (
                    <div className="reports-breakdown__item" key={item.id}>
                        <div className="reports-breakdown__copy"><strong>{item.label}</strong><small>{item.detail}</small></div>
                        {Number.isFinite(item.share) && item.share >= 0 && item.share <= 100 ? <DonutChart
                            size="small"
                            ariaLabel={`${item.label} : ${item.share.toLocaleString('fr-FR', { maximumFractionDigits: 1 })}% occupé et ${Number((100 - item.share).toFixed(1)).toLocaleString('fr-FR')}% vacant.`}
                            centerValue={`${item.share.toLocaleString('fr-FR', { maximumFractionDigits: 1 })} %`}
                            centerLabel="occupées"
                            segments={[
                                { id: 'occupied', label: 'Occupées', value: item.share, color: 'primary', displayValue: `${item.share.toLocaleString('fr-FR', { maximumFractionDigits: 1 })}%` },
                                { id: 'vacant', label: 'Vacantes', value: 100 - item.share, color: 'neutral', displayValue: `${Number((100 - item.share).toFixed(1)).toLocaleString('fr-FR')}%` },
                            ]}
                        /> : <p className="donut-chart__invalid" role="status">Données incohérentes : graphique non calculable.</p>}
                    </div>
                ))}
                {items.length === 0 && <p className="reports-empty">Aucune donnée pour cette période.</p>}
            </div>
        </Card>
    );
}
