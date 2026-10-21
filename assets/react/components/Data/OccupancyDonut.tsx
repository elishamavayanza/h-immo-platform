import { formatInteger, formatPercent } from '../../../utils/format.utils';
import { DonutChart } from './DonutChart';
import { resolveOccupancyCounts } from './donutChart';

export function OccupancyDonut({ occupied, total, size = 'medium', label = 'Unités' }: {
    occupied: number;
    total: number;
    size?: 'small' | 'medium' | 'large';
    label?: string;
}) {
    const counts = resolveOccupancyCounts(occupied, total);
    if (!counts.valid) return <p className="donut-chart__invalid" role="status">Données incohérentes : graphique non calculable.</p>;

    return <DonutChart
        size={size}
        className={counts.rate === null ? 'donut-chart--empty' : ''}
        ariaLabel={`${label} : ${counts.rate === null ? 'aucune unité sur ce périmètre' : formatPercent(counts.rate)}, ${formatInteger(counts.occupied)} occupées et ${formatInteger(counts.available)} vacantes sur ${formatInteger(counts.total)}.`}
        centerValue={counts.rate === null ? '—' : formatPercent(counts.rate)}
        centerLabel={counts.rate === null ? 'N/D' : 'occupées'}
        totalLabel={counts.rate === null ? 'Aucune unité' : `${formatInteger(counts.total)} unités au total`}
        segments={[
            { id: 'occupied', label: 'Occupées', value: counts.occupied, color: 'primary', displayValue: formatInteger(counts.occupied) },
            { id: 'available', label: 'Vacantes', value: counts.available, color: 'neutral', displayValue: formatInteger(counts.available) },
        ]}
    />;
}
