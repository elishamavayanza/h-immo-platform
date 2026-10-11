/**
 * types/index.ts — Rapport PATRON
 *
 * Miroirs TypeScript du contrat `PatronReportResponse`
 * (`src/Dto/Response/Report/PatronReportResponse.php` + items). Le contrôleur
 * renvoie l'objet rapport directement (pas d'enveloppe `Feedback`), avec des
 * propriétés camelCase.
 *
 * Les items d'occupation, d'impayé et de dépense sont **les mêmes classes**
 * que pour l'ADMIN_IMMOBILIER (`OccupancyItem`, `ArrearsItem`,
 * `ExpenseSummaryItem`) : on réutilise leurs types plutôt que de les
 * dupliquer. Les montants restent des chaînes décimales (jamais de flottant).
 */
import type { ArrearsItem, ExpenseSummaryItem, OccupancyItem } from '../../../admin_immobilier/reports/types';

export type { ArrearsItem, ExpenseSummaryItem, OccupancyItem };

/** Résumé financier d'une période (`FinancialSummaryItem`). */
export interface FinancialSummaryItem {
    /** Période `YYYY-MM`. */
    period: string;
    /** Revenus encaissés (loyers). */
    revenues: string;
    /** Dépenses de la période. */
    expenses: string;
    /** Loyers attendus (échéances générées). */
    expected: string;
    /** Résultat net (revenus - dépenses). */
    netResult: string;
    /** Devise ISO 4217 (`USD`/`CDF`). */
    currency: string;
}

/** Réponse brute de `GET /api/v1/reports/patron`. */
export interface PatronReport {
    organizationUuid: string;
    organizationName: string;
    periodCovered: string;
    generatedAt: string;
    financialSummary: FinancialSummaryItem[];
    occupancyByCity: OccupancyItem[];
    occupancyByParcel: OccupancyItem[];
    occupancyByBuilding: OccupancyItem[];
    arrears: ArrearsItem[];
    expensesByCategory: ExpenseSummaryItem[];
    expensesByCity: ExpenseSummaryItem[];
    totalRevenues: string;
    totalExpenses: string;
    totalArrears: string;
    currency: string;
}

/** Carte indicateur pour l'en-tête du rapport. */
export interface PatronReportsMetric {
    id: string;
    label: string;
    value: string;
    helper: string;
    tone: 'primary' | 'success' | 'warning' | 'info';
}

/** Données normalisées préparées pour l'affichage du rapport. */
export interface PatronReportsData {
    organizationName: string;
    periodCovered: string;
    currency: string;
    metrics: PatronReportsMetric[];
    cities: OccupancyItem[];
    parcels: OccupancyItem[];
    arrears: ArrearsItem[];
    expensesByCategory: ExpenseSummaryItem[];
    expensesByCity: ExpenseSummaryItem[];
    financialSummary: FinancialSummaryItem[];
}
