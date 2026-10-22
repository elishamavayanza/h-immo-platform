import { OccupancyDonut } from '../../../../components/Data/OccupancyDonut';

interface ReportOccupancyItem {
    label: string;
    occupiedUnits: number;
    totalUnits: number;
}

/** Ligne d'occupation partagée entre les rapports qui affichent des villes ou immeubles. */
export function ReportOccupancyRow({ item }: { item: ReportOccupancyItem }) {
    return (
        <div className="reports-breakdown__item">
            <div className="reports-breakdown__copy">
                <strong>{item.label}</strong>
                <small>{item.occupiedUnits} / {item.totalUnits} unités occupées</small>
            </div>
            <OccupancyDonut size="small" occupied={item.occupiedUnits} total={item.totalUnits} label={item.label} />
        </div>
    );
}
