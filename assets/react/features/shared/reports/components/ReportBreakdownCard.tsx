import { Card } from '../../../../components/UI/Card';
import type { ReportBreakdownItem } from '../types/report.types';

export function ReportBreakdownCard({ title, items }: { title: string; items: ReportBreakdownItem[] }) {
    return (
        <Card className="reports-panel reports-breakdown" padding="medium">
            <div className="reports-panel__heading"><div><h2>{title}</h2><p>Répartition des indicateurs sur le périmètre sélectionné.</p></div></div>
            <div className="reports-breakdown__list">
                {items.map((item) => (
                    <div className="reports-breakdown__item" key={item.id}>
                        <div className="reports-breakdown__copy"><strong>{item.label}</strong><small>{item.detail}</small></div>
                        <span className="reports-breakdown__amount">{item.amount}</span>
                        <progress value={item.share} max={100} aria-label={`${item.label} : ${item.share}%`} />
                    </div>
                ))}
                {items.length === 0 && <p className="reports-empty">Aucune donnée pour cette période.</p>}
            </div>
        </Card>
    );
}
