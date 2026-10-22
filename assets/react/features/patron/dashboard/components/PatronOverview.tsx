import { formatInteger, formatMoney, formatPercent } from '../../../../../utils/format.utils';
import type { PatronDashboardData } from '../types/patronDashboard.types';
import { OrganizationDashboardOverview } from '../../../shared/dashboard/components/OrganizationDashboardOverview';
import { absoluteCents, amountBarPercent } from '../../../shared/reports/utils/amountChart';

/** Prépare les données organisationnelles du Patron pour la trame dashboard partagée. */
export function PatronOverview({ data }: { data: PatronDashboardData }) {
    const occupied = data.cities.reduce((total, item) => total + item.occupiedUnits, 0);
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
                title: 'Occupation du patrimoine',
                summary: `${formatInteger(data.totalUnits)} unités sur le périmètre`,
                donutLabel: 'Patrimoine de l’organisation',
                occupied,
                total: data.totalUnits,
                footer: `Devise de référence : ${data.currency} · ${data.periodCovered.replace(' to ', ' → ')}`,
            }}
            occupancyChart={{
                title: 'Occupation par ville',
                description: 'Part des unités occupées dans chaque ville.',
                emptyMessage: 'Aucune ville à afficher.',
                items: data.cities.map((item) => ({
                    id: item.levelUuid ?? item.label,
                    label: item.label,
                    detail: `${formatInteger(item.occupiedUnits)} / ${formatInteger(item.totalUnits)} unités occupées`,
                    value: formatPercent(item.occupancyRate),
                    share: item.occupancyRate,
                    occupied: item.occupiedUnits,
                    total: item.totalUnits,
                })),
            }}
            arrears={{ title: 'Impayés à recouvrer', emptyLabel: 'à traiter', items: data.arrears }}
            expenses={{
                title: 'Dépenses par ville',
                description: `Répartition sur la période · ${data.currency}`,
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
