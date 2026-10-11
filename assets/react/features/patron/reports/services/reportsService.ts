/**
 * reportsService — Rapport PATRON branché sur l'API réelle.
 *
 * Contrat HTTP : `GET /api/v1/reports/patron?organizationUuid=…` avec
 * `periodFrom` / `periodTo` / `cityUuid` optionnels
 * (`ReportController::patronReport`). Le contrôleur sérialise
 * `PatronReportResponse` directement (corps NON enveloppé dans `Feedback`).
 *
 * Le backend rejoue `resolveReportOrganization(…, PATRON)` : une organisation
 * dont l'appelant n'est pas le Patron renvoie 400/403/404, jamais ses données.
 * Aucun filtrage local n'est donc nécessaire.
 */
import { apiClient } from '../../../../../services/api/client';
import { tokenStorage } from '../../../../../services/storage/storage.service';
import { sumInCurrency } from '../../../admin_immobilier/reports/services/reportsService';
import { formatInteger, formatMoney, formatPercent } from '../../../../../utils/format.utils';
import type {
    PatronReport,
    PatronReportsData,
    PatronReportsMetric,
} from '../types';

/** Filtres optionnels du rapport (dates ISO `YYYY-MM-DD`, UUID de ville). */
export interface PatronReportQuery {
    periodFrom?: string;
    periodTo?: string;
    cityUuid?: string;
}

/** Récupère le rapport brut de l'organisation (borné côté backend). */
export async function fetchPatronReport(organizationUuid: string, query: PatronReportQuery = {}): Promise<PatronReport> {
    const { data } = await apiClient.get<PatronReport>('/v1/reports/patron', {
        params: {
            organizationUuid,
            ...(query.periodFrom ? { periodFrom: query.periodFrom } : {}),
            ...(query.periodTo ? { periodTo: query.periodTo } : {}),
            ...(query.cityUuid ? { cityUuid: query.cityUuid } : {}),
        },
    });

    return data;
}

/**
 * Télécharge le rapport au format PDF.
 *
 * `window.open` ne peut pas porter l'en-tête `Authorization` : on récupère le
 * PDF en `fetch` authentifié puis on déclenche le téléchargement depuis le
 * blob. Le backend rejoue les mêmes contrôles (PATRON de l'organisation).
 */
export async function downloadPatronReportPdf(organizationUuid: string, query: PatronReportQuery = {}): Promise<void> {
    const accessToken = tokenStorage.getAccessToken();
    if (!accessToken) {
        throw new Error('Non authentifié : jeton d’accès manquant');
    }

    const params = new URLSearchParams({ organizationUuid, format: 'pdf' });
    if (query.periodFrom) params.set('periodFrom', query.periodFrom);
    if (query.periodTo) params.set('periodTo', query.periodTo);
    if (query.cityUuid) params.set('cityUuid', query.cityUuid);

    const response = await fetch(`/api/v1/reports/patron?${params.toString()}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/pdf',
            'X-Requested-With': 'XMLHttpRequest',
            'Authorization': `Bearer ${accessToken}`,
        },
    });

    if (!response.ok) {
        throw new Error(`Échec du téléchargement (${response.status})`);
    }

    const blob = await response.blob();
    const urlBlob = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = urlBlob;
    link.download = `rapport-organisation-${new Date().toISOString().split('T')[0]}.pdf`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(urlBlob);
}

/** Somme des loyers attendus sur la période, bornée à la devise du rapport. */
export function sumExpected(report: PatronReport): string {
    return sumInCurrency(report.financialSummary, report.financialSummary.map((item) => item.expected), report.currency);
}

/** Normalise la réponse API vers les données affichées du rapport. */
export function normalizePatronReport(report: PatronReport): PatronReportsData {
    // Le DTO n'expose pas de taux global : on le reconstitue depuis l'occupation
    // par ville (mêmes unités, donc même échelle que le rapport).
    const totalUnits = report.occupancyByCity.reduce((total, item) => total + item.totalUnits, 0);
    const occupiedUnits = report.occupancyByCity.reduce((total, item) => total + item.occupiedUnits, 0);
    const occupancyRate = totalUnits > 0 ? (occupiedUnits / totalUnits) * 100 : 0;

    const expecteTotal = sumInCurrency(
        report.financialSummary,
        report.financialSummary.map((item) => item.expected),
        report.currency,
    );

    const metrics: PatronReportsMetric[] = [
        {
            id: 'revenues',
            label: 'Revenus encaissés',
            value: formatMoney(report.totalRevenues, report.currency),
            helper: expecteTotal !== '0.00'
                ? `sur ${formatMoney(expecteTotal, report.currency)} attendus`
                : 'Aucun loyer attendu sur la période',
            tone: 'success',
        },
        {
            id: 'expenses',
            label: 'Dépenses',
            value: formatMoney(report.totalExpenses, report.currency),
            helper: report.expensesByCategory.length > 0
                ? `${formatInteger(report.expensesByCategory.length)} catégorie(s)`
                : 'Aucune dépense sur la période',
            tone: 'info',
        },
        {
            id: 'arrears',
            label: 'Impayés',
            value: formatMoney(report.totalArrears, report.currency),
            helper: report.arrears.length > 0
                ? `${formatInteger(report.arrears.length)} échéance(s) en retard`
                : 'Aucune échéance en retard',
            tone: report.arrears.length > 0 ? 'warning' : 'success',
        },
        {
            id: 'occupancy',
            label: 'Occupation globale',
            value: formatPercent(occupancyRate),
            helper: totalUnits > 0
                ? `${formatInteger(occupiedUnits)} unités occupées sur ${formatInteger(totalUnits)}`
                : 'Aucune unité sur le périmètre',
            tone: 'primary',
        },
    ];

    return {
        organizationName: report.organizationName,
        periodCovered: report.periodCovered,
        currency: report.currency,
        metrics,
        cities: report.occupancyByCity,
        parcels: report.occupancyByParcel,
        arrears: report.arrears,
        expensesByCategory: report.expensesByCategory,
        expensesByCity: report.expensesByCity,
        financialSummary: report.financialSummary,
    };
}

/** Récupère et normalise le rapport de l'organisation en une seule étape. */
export async function fetchPatronReports(organizationUuid: string, query: PatronReportQuery = {}): Promise<PatronReportsData> {
    const report = await fetchPatronReport(organizationUuid, query);

    return normalizePatronReport(report);
}
