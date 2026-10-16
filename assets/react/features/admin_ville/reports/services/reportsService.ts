/**
 * reportsService — Rapport ADMIN_VILLE branché sur l'API réelle.
 *
 * Contrat HTTP : `GET /api/v1/reports/admin-ville/{cityUuid}` avec
 * `periodFrom` / `periodTo` optionnels (`ReportController::adminVilleReport`).
 * Le contrôleur sérialise `AdminVilleReportResponse` directement (le corps
 * n'est PAS enveloppé dans `Feedback`).
 *
 * Le backend contrôle `checkCityAccess` avant toute lecture : une ville non
 * affectée à l'appelant renvoie un 403, jamais les données d'une autre ville.
 * Aucun filtrage local n'est donc nécessaire, on affiche ce que l'API renvoie.
 */
import { apiClient } from '../../../../../services/api/client';
import { sumInCurrency } from '../../../admin_immobilier/reports/services/reportsService';
import { formatInteger, formatMoney, formatPercent } from '../../../../../utils/format.utils';
import type {
    AdminVilleReport,
    AdminVilleReportsData,
    AdminVilleReportsMetric,
} from '../types';

/** Bornes de période optionnelles du rapport (format ISO `YYYY-MM-DD`). */
export interface AdminVilleReportQuery {
    periodFrom?: string;
    periodTo?: string;
}

/** Récupère le rapport brut d'une ville (borné côté backend par `checkCityAccess`). */
export async function fetchAdminVilleReport(
    cityUuid: string,
    query: AdminVilleReportQuery = {}
): Promise<AdminVilleReport> {
    const { data } = await apiClient.get<AdminVilleReport>(`/v1/reports/admin-ville/${cityUuid}`, {
        params: {
            ...(query.periodFrom ? { periodFrom: query.periodFrom } : {}),
            ...(query.periodTo ? { periodTo: query.periodTo } : {}),
        },
    });

    return data;
}

/**
 * Télécharge le rapport de la ville au format PDF.
 *
 * `window.open` ne peut pas porter l'en-tête `Authorization` : on récupère
 * donc le PDF en `fetch` authentifié, puis on déclenche le téléchargement
 * depuis le blob. Le backend rejoue `checkCityAccess` + `VIEW_REPORT`, la
 * ville non affectée échoue en 403 sans produire de fichier.
 */
export async function downloadAdminVilleReportPdf(
    cityUuid: string,
    query: AdminVilleReportQuery = {}
): Promise<void> {
    const { data: blob } = await apiClient.getBlob(`/v1/reports/admin-ville/${cityUuid}`, {
        params: {
            format: 'pdf',
            ...(query.periodFrom ? { periodFrom: query.periodFrom } : {}),
            ...(query.periodTo ? { periodTo: query.periodTo } : {}),
        },
        headers: { Accept: 'application/pdf' },
    });
    const urlBlob = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = urlBlob;
    link.download = `rapport-admin-ville-${new Date().toISOString().split('T')[0]}.pdf`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(urlBlob);
}

/** Normalise la réponse API vers les données affichées du rapport. */
export function normalizeAdminVilleReport(report: AdminVilleReport): AdminVilleReportsData {
    // `occupancyByParcel` alimente le taux global côté backend : on en déduit
    // le nombre d'unités occupées affiché pour rester cohérent.
    const occupied = report.occupancyByParcel.reduce((total, item) => total + item.occupiedUnits, 0);

    const arrearsTotal = sumInCurrency(
        report.arrears,
        report.arrears.map((item) => item.arrears),
        report.currency
    );

    const metrics: AdminVilleReportsMetric[] = [
        {
            id: 'units',
            label: 'Unités gérées',
            value: formatInteger(report.totalUnits),
            helper: report.totalUnits > 0
                ? `${formatInteger(occupied)} unités occupées`
                : 'Aucune unité enregistrée',
            tone: 'primary',
        },
        {
            id: 'occupancy',
            label: 'Taux d’occupation',
            value: formatPercent(report.occupancyRate),
            helper: 'Part des unités occupées de votre ville',
            tone: report.occupancyRate >= 70 ? 'success' : 'warning',
        },
        {
            id: 'arrears',
            label: 'Impayés à recouvrer',
            value: formatInteger(report.arrears.length),
            helper: report.arrears.length > 0
                ? `${formatMoney(arrearsTotal, report.currency)} en retard`
                : 'Aucune échéance en retard',
            tone: report.arrears.length > 0 ? 'warning' : 'success',
        },
        {
            id: 'expenses',
            label: 'Dépenses de la ville',
            value: formatMoney(report.totalExpenses, report.currency),
            helper: report.cityExpenses.length > 0
                ? `sur ${formatInteger(report.cityExpenses.length)} poste(s)`
                : 'Aucune dépense sur la période',
            tone: 'info',
        },
    ];

    return {
        cityName: report.cityName,
        periodCovered: report.periodCovered,
        currency: report.currency,
        metrics,
        buildings: report.occupancyByBuilding,
        parcels: report.occupancyByParcel,
        arrears: report.arrears,
        upcomingLeaseExpirations: report.upcomingLeaseExpirations,
        expenses: report.cityExpenses,
        workers: report.workers,
    };
}

/** Récupère et normalise le rapport d'une ville en une seule étape. */
export async function fetchAdminVilleReports(
    cityUuid: string,
    query: AdminVilleReportQuery = {}
): Promise<AdminVilleReportsData> {
    const report = await fetchAdminVilleReport(cityUuid, query);

    return normalizeAdminVilleReport(report);
}
