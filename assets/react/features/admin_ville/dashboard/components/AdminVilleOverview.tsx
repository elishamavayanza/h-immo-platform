import { formatInteger, formatMoney, formatPercent } from '../../../../../utils/format.utils';
import type { AdminVilleDashboardData } from '../types/adminVilleDashboard.types';
import { OrganizationDashboardOverview } from '../../../shared/dashboard/components/OrganizationDashboardOverview';
import { absoluteCents, amountBarPercent } from '../../../shared/reports/utils/amountChart';

/** Prépare les données de la ville active pour la trame dashboard partagée. */
export function AdminVilleOverview({ data }: { data: AdminVilleDashboardData }) {
    const occupied = data.buildings.reduce((total, item) => total + item.occupiedUnits, 0);
    const expenses = data.expenses.filter((item) => item.currency === data.currency);
    const maximumExpense = expenses.reduce((maximum, item) => {
        const amount = absoluteCents(item.totalAmount);
        return amount > maximum ? amount : maximum;
    }, 0n);

    return (
        <OrganizationDashboardOverview
            metrics={data.metrics}
            upcomingLeaseExpirations={data.upcomingLeaseExpirations}
            occupancy={{
                title: `Situation de ${data.cityName}`,
                summary: 'Occupation des unités de votre périmètre',
                donutLabel: `Ville ${data.cityName}`,
                occupied,
                total: data.totalUnits,
                footer: `${formatInteger(occupied)} unités occupées sur ${formatInteger(data.totalUnits)} · Devise : ${data.currency}`,
            }}
            occupancyChart={{
                title: 'Occupation par immeuble',
                description: 'Part des unités occupées dans votre ville.',
                emptyMessage: 'Aucun immeuble à afficher.',
                items: data.buildings.map((item) => ({
                    id: item.levelUuid ?? item.label,
                    label: item.label,
                    detail: `${formatInteger(item.occupiedUnits)} / ${formatInteger(item.totalUnits)} unités occupées`,
                    value: formatPercent(item.occupancyRate),
                    share: item.occupancyRate,
                    occupied: item.occupiedUnits,
                    total: item.totalUnits,
                })),
            }}
            arrears={{ title: 'Impayés à suivre', emptyLabel: 'à suivre', items: data.arrears }}
            expenses={{
                title: 'Dépenses de la ville',
                description: `Répartition par niveau · ${data.currency}`,
                emptyMessage: 'Aucune dépense dans la devise de référence sur cette période.',
                items: expenses.map((item) => ({
                    id: `${item.levelUuid ?? item.levelLabel}-${item.category}`,
                    label: item.levelLabel,
                    detail: item.category === 'ALL' ? 'Toutes catégories' : item.category,
                    value: formatMoney(item.totalAmount, item.currency),
                    share: amountBarPercent(item.totalAmount, maximumExpense),
                })),
            }}
        />
    );
}
