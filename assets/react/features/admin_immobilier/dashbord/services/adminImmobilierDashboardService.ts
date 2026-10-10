/**
 * adminImmobilierDashboardService — Tableau de bord branché sur l'API.
 *
 * Source unique : `GET /v1/reports/admin-immobilier?organizationUuid=…`
 * (`ReportController::adminImmobilierReport`), le même rapport que la page
 * Rapports, projeté ici en indicateurs d'activité. Le tableau de bord n'a
 * pas d'endpoint dédié pour ADMIN_IMMOBILIER : inventer un second chemin
 * d'agrégation dupliquerait la logique d'isolation sans aucun bénéfice.
 */
import { formatInteger, formatMoney, formatPercent } from '../../../../../utils/format.utils';
import { fetchAdminImmobilierReport, sumInCurrency } from '../../reports/services/reportsService';
import type { AdminImmobilierDashboardData, AdminImmobilierMetric } from '../types/adminImmobilierDashboard.types';

/** Construit les 4 indicateurs du tableau de bord à partir du rapport. */
function buildMetrics(
    totalUnits: number,
    globalOccupancyRate: number,
    arrearsCount: number,
    arrearsTotal: string,
    expenseTotal: string,
    expenseLevels: number,
    currency: string,
): AdminImmobilierMetric[] {
    return [
        {
            id: 'units',
            label: 'Unités gérées',
            value: formatInteger(totalUnits),
            detail: totalUnits > 0 ? 'sur le périmètre de l’organisation' : 'Aucune unité enregistrée',
            tone: 'primary',
        },
        {
            id: 'occupancy',
            label: 'Occupation globale',
            value: formatPercent(globalOccupancyRate),
            detail: 'unités occupées / total',
            tone: globalOccupancyRate >= 70 ? 'success' : 'warning',
        },
        {
            id: 'arrears',
            label: 'Impayés à traiter',
            value: formatInteger(arrearsCount),
            detail: arrearsCount > 0
                ? `${formatMoney(arrearsTotal, currency)} à recouvrer`
                : 'Aucune échéance en retard',
            tone: arrearsCount > 0 ? 'warning' : 'success',
        },
        {
            id: 'expenses',
            label: 'Dépenses liées aux biens',
            value: formatMoney(expenseTotal, currency),
            detail: expenseLevels > 0 ? `réparties sur ${formatInteger(expenseLevels)} niveau(x)` : 'Aucune dépense sur la période',
            tone: 'info',
        },
    ];
}

/** Récupère et projette le rapport immobilier de l'organisation active. */
export async function fetchAdminImmobilierDashboard(organizationUuid: string): Promise<AdminImmobilierDashboardData> {
    const report = await fetchAdminImmobilierReport(organizationUuid);

    const arrearsTotal = sumInCurrency(report.arrears, report.arrears.map((item) => item.arrears), report.currency);
    const expenseTotal = sumInCurrency(
        report.propertyExpenses,
        report.propertyExpenses.map((entry) => entry.totalAmount),
        report.currency,
    );

    return {
        organizationName: report.organizationName,
        periodCovered: report.periodCovered,
        currency: report.currency,
        totalUnits: report.totalUnits,
        globalOccupancyRate: report.globalOccupancyRate,
        metrics: buildMetrics(
            report.totalUnits,
            report.globalOccupancyRate,
            report.arrears.length,
            arrearsTotal,
            expenseTotal,
            report.propertyExpenses.length,
            report.currency,
        ),
        buildings: report.occupancyByBuilding,
        arrears: report.arrears,
        expenses: report.propertyExpenses,
    };
}