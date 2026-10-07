import { Badge } from '../../../../components/UI/Badge';
import { Card } from '../../../../components/UI/Card';
import type { ReportAttentionItem } from '../types/report.types';

export function ReportAttentionCard({ title, items }: { title: string; items: ReportAttentionItem[] }) {
    return (
        <Card className="reports-panel reports-attention" padding="medium">
            <div className="reports-panel__heading"><div><h2>{title}</h2><p>Éléments à consulter dans votre espace.</p></div></div>
            <ul className="reports-attention__list">
                {items.map((item) => (
                    <li key={item.id}>
                        <div><strong>{item.label}</strong><small>{item.detail}</small></div>
                        <span className="reports-attention__amount">{item.amount}</span>
                        <Badge variant={item.status.includes('Action') || item.status.includes('valider') || item.status.includes('recouvrer') ? 'warning' : 'info'} size="small">{item.status}</Badge>
                    </li>
                ))}
                {items.length === 0 && <li className="reports-empty">Aucun élément à signaler.</li>}
            </ul>
        </Card>
    );
}
