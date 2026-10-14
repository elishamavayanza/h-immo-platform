/**
 * patronDashboardService — Tableau de bord PATRON branché sur l'API.
 *
 * Source unique : `GET /v1/reports/patron?organizationUuid=…`
 * (`ReportController::patronReport`), le même rapport que la page Rapports,
 * projeté ici en indicateurs synthétiques. Le dashboard Patron n'a pas
 * d'endpoint dédié : en inventer un second dupliquerait la logique
 * d'isolation sans bénéfice.
 */
import { formatInteger, formatMoney, formatPercent } from '../../../../../utils/format.utils';
import { fetchPatronReport, sumExpected } from '../../reports/services/reportsService';
import type { PatronDashboardData, PatronDashboardMetric } from '../types/patronDashboard.types';

/** Construit les 4 indicateurs du tableau de bord à partir du rapport. */
function buildMetrics(
    totalRevenues: string,
    expected: string,
    totalExpenses: string,
    expensesCount: number,
    arrearsCount: number,
    totalArrears: string,
    occupancyRate: number,
    occupiedUnits: number,
    totalUnits: number,
    currency: string,
): PatronDashboardMetric[] {
    return [
        {
            id: 'revenues',
            label: 'Revenus encaissés',
            value: formatMoney(totalRevenues, currency),
            detail: expected !== '0.00' ? `sur ${formatMoney(expected, currency)} attendus` : 'Aucun loyer attendu',
            tone: 'success',
        },
        {
            id: 'expenses',
            label: 'Dépenses',
            value: formatMoney(totalExpenses, currency),
            detail: expensesCount > 0 ? `réparties sur ${formatInteger(expensesCount)} ville(s)` : 'Aucune dépense sur la période',
            tone: 'info',
        },
        {
            id: 'arrears',
            label: 'Impayés à recouvrer',
            value: formatMoney(totalArrears, currency),
            detail: arrearsCount > 0 ? `${formatInteger(arrearsCount)} échéance(s) en retard` : 'Aucune échéance en retard',
            tone: arrearsCount > 0 ? 'warning' : 'success',
        },
        {
            id: 'occupancy',
            label: 'Occupation globale',
            value: formatPercent(occupancyRate),
            detail: totalUnits > 0 ? `${formatInteger(occupiedUnits)} unités occupées sur ${formatInteger(totalUnits)}` : 'Aucune unité',
            tone: 'primary',
        },
    ];
}

/** Récupère et projette le rapport de l'organisation active. */
export async function fetchPatronDashboard(organizationUuid: string): Promise<PatronDashboardData> {
    const report = await fetchPatronReport(organizationUuid);

    const totalUnits = report.occupancyByCity.reduce((total, item) => total + item.totalUnits, 0);
    const occupiedUnits = report.occupancyByCity.reduce((total, item) => total + item.occupiedUnits, 0);
    const occupancyRate = totalUnits > 0 ? (occupiedUnits / totalUnits) * 100 : 0;

    return {
        organizationName: report.organizationName,
        periodCovered: report.periodCovered,
        currency: report.currency,
        totalUnits,
        globalOccupancyRate: occupancyRate,
        metrics: buildMetrics(
            report.totalRevenues,
            sumExpected(report),
            report.totalExpenses,
            report.expensesByCity.length,
            report.arrears.length,
            report.totalArrears,
            occupancyRate,
            occupiedUnits,
            totalUnits,
            report.currency,
        ),
        cities: report.occupancyByCity,
        arrears: report.arrears,
        upcomingLeaseExpirations: report.upcomingLeaseExpirations,
        expenses: report.expensesByCity,
    };
}
