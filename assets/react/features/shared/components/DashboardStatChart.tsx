import { Card } from '../../../components/UI/Card';

export interface DashboardStatChartItem {
    id: string;
    label: string;
    detail: string;
    value: string;
    /** Taille relative de la barre, entre 0 et 100. */
    share: number;
}

interface DashboardStatChartProps {
    title: string;
    description: string;
    items: DashboardStatChartItem[];
    emptyMessage: string;
}

/** Graphique horizontal accessible, compact et réutilisable sur les tableaux de bord. */
export function DashboardStatChart({ title, description, items, emptyMessage }: DashboardStatChartProps) {
    const visibleItems = items.slice(0, 6);

    return (
        <Card className="organization-panel organization-stat-chart" padding="medium">
            <div className="organization-stat-chart__heading">
                <div><h2>{title}</h2><p>{description}</p></div>
            </div>
            {items.length > 0 ? (
                <ul className="organization-stat-chart__list">
                    {visibleItems.map((item) => (
                        <li className="organization-stat-chart__item" key={item.id}>
                            <div className="organization-stat-chart__labels">
                                <span><strong>{item.label}</strong><small>{item.detail}</small></span>
                                <b>{item.value}</b>
                            </div>
                            <div className="organization-stat-chart__track" role="progressbar" aria-label={item.label} aria-valuemin={0} aria-valuemax={100} aria-valuenow={Math.round(Math.min(Math.max(item.share, 0), 100))}>
                                <span style={{ width: `${Math.min(Math.max(item.share, 0), 100)}%` }} />
                            </div>
                        </li>
                    ))}
                </ul>
            ) : <p className="organization-stat-chart__empty">{emptyMessage}</p>}
            {items.length > visibleItems.length && <p className="organization-stat-chart__note">Affichage des {visibleItems.length} premiers éléments sur {items.length}.</p>}
        </Card>
    );
}
