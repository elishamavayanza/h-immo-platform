/**
 * adminVilleDashboardService — Tableau de bord branché sur l'API réelle.
 *
 * Source unique : `GET /v1/reports/admin-ville/{cityUuid}`
 * (`ReportController::adminVilleReport`), le même rapport que la page
 * Rapports, projeté ici en indicateurs d'activité. Pas d'endpoint dédié :
 * dupliquer l'agrégation reviendrait à dupliquer la logique d'isolation.
 */
import { formatInteger, formatMoney, formatPercent } from '../../../../../utils/format.utils';
import { sumInCurrency } from '../../../admin_immobilier/reports/services/reportsService';
import { fetchAdminVilleReport } from '../../reports/services/reportsService';
import type { AdminVilleDashboardData, AdminVilleMetric } from '../types/adminVilleDashboard.types';

/** Construit les 4 indicateurs du tableau de bord à partir du rapport. */
function buildMetrics(
    totalUnits: number,
    occupancyRate: number,
    arrearsCount: number,
    arrearsTotal: string,
    expenseTotal: string,
    expenseLevels: number,
    currency: string,
): AdminVilleMetric[] {
    return [
        {
            id: 'units',
            label: 'Unités dans mon périmètre',
            value: formatInteger(totalUnits),
            detail: totalUnits > 0 ? 'sur le périmètre de la ville' : 'Aucune unité enregistrée',
            tone: 'primary',
        },
        {
            id: 'occupancy',
            label: 'Taux d’occupation',
            value: formatPercent(occupancyRate),
            detail: 'unités occupées / total',
            tone: occupancyRate >= 70 ? 'success' : 'warning',
        },
        {
            id: 'arrears',
            label: 'Loyers à suivre',
            value: formatInteger(arrearsCount),
            detail: arrearsCount > 0
                ? `${formatMoney(arrearsTotal, currency)} à recouvrer`
                : 'Aucune échéance en retard',
            tone: arrearsCount > 0 ? 'warning' : 'success',
        },
        {
            id: 'expenses',
            label: 'Dépenses de la ville',
            value: formatMoney(expenseTotal, currency),
            detail: expenseLevels > 0 ? `réparties sur ${formatInteger(expenseLevels)} niveau(x)` : 'Aucune dépense sur la période',
            tone: 'info',
        },
    ];
}

/** Récupère et projette le rapport d'une ville en indicateurs de tableau de bord. */
export async function fetchAdminVilleDashboard(cityUuid: string): Promise<AdminVilleDashboardData> {
    const report = await fetchAdminVilleReport(cityUuid);

    const arrearsTotal = sumInCurrency(report.arrears, report.arrears.map((item) => item.arrears), report.currency);
    const expenseTotal = sumInCurrency(
        report.cityExpenses,
        report.cityExpenses.map((entry) => entry.totalAmount),
        report.currency,
    );

    return {
        cityName: report.cityName,
        periodCovered: report.periodCovered,
        currency: report.currency,
        totalUnits: report.totalUnits,
        occupancyRate: report.occupancyRate,
        metrics: buildMetrics(
            report.totalUnits,
            report.occupancyRate,
            report.arrears.length,
            arrearsTotal,
            expenseTotal,
            report.cityExpenses.length,
            report.currency,
        ),
        buildings: report.occupancyByBuilding,
        arrears: report.arrears,
        upcomingLeaseExpirations: report.upcomingLeaseExpirations,
        expenses: report.cityExpenses,
    };
}
