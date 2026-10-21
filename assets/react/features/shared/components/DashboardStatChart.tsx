import { Card } from '../../../components/UI/Card';
import { OccupancyDonut } from '../../../components/Data/OccupancyDonut';

export interface DashboardStatChartItem {
    id: string;
    label: string;
    detail: string;
    value: string;
    /** Taille relative de la barre, entre 0 et 100. */
    share: number;
    /** Présent uniquement pour un taux d’occupation (les montants restent comparés par barres). */
    occupied?: number;
    total?: number;
}

interface DashboardStatChartProps {
    title: string;
    description: string;
    items: DashboardStatChartItem[];
    emptyMessage: string;
}

/** Graphique horizontal accessible, compact et réutilisable sur les tableaux de bord. */
export function DashboardStatChart({ title, description, items, emptyMessage }: DashboardStatChartProps) {
    const isOccupancy = items.some((item) => item.occupied !== undefined && item.total !== undefined);
    const isScrollable = isOccupancy || items.length > 6;

    return (
            <Card className={`organization-panel organization-stat-chart${isOccupancy ? ' organization-stat-chart--occupancy' : ''}${isScrollable ? ' organization-stat-chart--scrollable' : ''}`} padding="medium">
            <div className="organization-stat-chart__heading">
                <div><h2>{title}</h2><p>{description}</p></div>
            </div>
            {items.length > 0 ? (
                <ul className="organization-stat-chart__list" aria-label={title} tabIndex={0}>
                    {items.map((item) => (
                        <li className="organization-stat-chart__item" key={item.id}>
                            <div className="organization-stat-chart__labels">
                                <span><strong>{item.label}</strong><small>{item.detail}</small></span>
                                {item.occupied === undefined && <b>{item.value}</b>}
                            </div>
                            {item.occupied !== undefined && item.total !== undefined ? <OccupancyDonut size="small" occupied={item.occupied} total={item.total} label={item.label} /> : <div className="organization-stat-chart__track" role="img" aria-label={`${item.label} : ${item.value}, comparaison relative`}>
                                <span style={{ width: `${Math.min(Math.max(item.share, 0), 100)}%` }} />
                            </div>}
                        </li>
                    ))}
                </ul>
            ) : <p className="organization-stat-chart__empty">{emptyMessage}</p>}
            {isScrollable && items.length > 6 && <p className="organization-stat-chart__note">Défilez dans la liste pour consulter les {items.length} éléments.</p>}
        </Card>
    );
}
