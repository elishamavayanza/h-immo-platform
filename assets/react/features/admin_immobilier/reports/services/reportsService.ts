/**
 * reportsService — Rapport ADMIN_IMMOBILIER branché sur l'API réelle.
 *
 * Contrat HTTP : `GET /api/v1/reports/admin-immobilier?organizationUuid=…`
 * (`src/Controller/Api/Report/ReportController::adminImmobilierReport`).
 * Le contrôleur sérialise `AdminImmobilierReportResponse` directement (le
 * corps n'est PAS enveloppé dans `Feedback`) : les `apiClient.get` ci-dessous
 * typent donc la réponse comme l'objet rapport lui-même.
 *
 * Le rapport étant borné côté backend à l'Organization de l'appelant, aucun
 * filtrage local par UUID n'est nécessaire : on demande l'organization
 * active du contexte et on affiche ce que l'API renvoie.
 */
import { apiClient } from '../../../../../services/api/client';
import { formatInteger, formatMoney, formatPercent } from '../../../../../utils/format.utils';
import type {
    AdminImmobilierReport,
    AdminImmobilierReportsData,
    AdminImmobilierReportsMetric,
} from '../types';

/** Récupère le rapport brut de l'organization active. */
export async function fetchAdminImmobilierReport(organizationUuid: string): Promise<AdminImmobilierReport> {
    const { data } = await apiClient.get<AdminImmobilierReport>('/v1/reports/admin-immobilier', {
        params: { organizationUuid },
    });

    return data;
}

/** Décimale "12.50" → nombre de centimes (entier, pas de calcul en flottant). */
function parseCents(amount: string): number {
    const [whole, fraction = ''] = String(amount).split('.');
    const fractionPadded = `${fraction}00`.slice(0, 2);

    return (parseInt(whole || '0', 10) * 100) + parseInt(fractionPadded || '0', 10);
}

/** Centimes (entier) → chaîne décimale "18450.50". */
function centsToDecimal(cents: number): string {
    const sign = cents < 0 ? '-' : '';
    const absolute = Math.abs(cents);

    return `${sign}${Math.trunc(absolute / 100)}.${String(absolute % 100).padStart(2, '0')}`;
}

/**
 * Somme les montants d'une liste, bornée à une devise donnée (aucun mélange
 * de devises). Renvoie la chaîne décimale prête à être formatée. Exposé au
 * tableau de bord, qui agrège les mêmes lignes.
 */
export function sumInCurrency(entries: ReadonlyArray<{ currency: string }>, amounts: string[], currency: string): string {
    let totalCents = 0;
    entries.forEach((entry, index) => {
        if (entry.currency !== currency) return;
        totalCents += parseCents(amounts[index]);
    });

    return centsToDecimal(totalCents);
}

/** Normalise la réponse API vers les données affichées du rapport. */
export function normalizeAdminImmobilierReport(report: AdminImmobilierReport): AdminImmobilierReportsData {
    // `occupancyByParcel` est la source de `globalOccupancyRate` côté backend :
    // on en déduit l'occurrence occupée affichée pour rester cohérent.
    const occupied = report.occupancyByParcel.reduce((total, item) => total + item.occupiedUnits, 0);
    const buildingCount = report.occupancyByBuilding.length;

    const expenseTotal = sumInCurrency(report.propertyExpenses, report.propertyExpenses.map((entry) => entry.totalAmount), report.currency);

    const metrics: AdminImmobilierReportsMetric[] = [
        {
            id: 'occupancy',
            label: 'Occupation globale',
            value: formatPercent(report.globalOccupancyRate),
            helper: report.totalUnits > 0
                ? `${formatInteger(occupied)} unités occupées sur ${formatInteger(report.totalUnits)}`
                : 'Aucune unité sur le périmètre',
            tone: 'primary',
        },
        {
            id: 'units',
            label: 'Unités gérées',
            value: formatInteger(report.totalUnits),
            helper: buildingCount > 0 ? `${formatInteger(buildingCount)} immeubles` : 'Aucun immeuble',
            tone: 'info',
        },
        {
            id: 'arrears',
            label: 'Impayés à recouvrer',
            value: formatInteger(report.arrears.length),
            helper: report.arrears.length > 0
                ? `${formatMoney(sumInCurrency(report.arrears, report.arrears.map((item) => item.arrears), report.currency), report.currency)} en retard`
                : 'Aucune échéance en retard',
            tone: 'warning',
        },
        {
            id: 'expenses',
            label: 'Dépenses liées aux biens',
            value: formatMoney(expenseTotal, report.currency),
            helper: report.propertyExpenses.length > 0
                ? `sur ${formatInteger(report.propertyExpenses.length)} niveau(x) du patrimoine`
                : 'Aucune dépense sur la période',
            tone: 'success',
        },
    ];

    return {
        organizationName: report.organizationName,
        periodCovered: report.periodCovered,
        currency: report.currency,
        metrics,
        buildings: report.occupancyByBuilding,
        arrears: report.arrears,
        expenses: report.propertyExpenses,
        // 6 derniers mois : le graphe de la page ne montre que ce revenu.
        evolution: report.occupancyEvolution.slice(-6),
    };
}

/** Historique : récupère et normalise le rapport en une seule étape. */
export async function fetchAdminImmobilierReports(organizationUuid: string): Promise<AdminImmobilierReportsData> {
    const report = await fetchAdminImmobilierReport(organizationUuid);

    return normalizeAdminImmobilierReport(report);
}