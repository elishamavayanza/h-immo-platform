/**
 * types/index.ts — Rapport ADMIN_IMMOBILIER
 *
 * Miroirs TypeScript du contrat `AdminImmobilierReportResponse`
 * (`src/Dto/Response/Report/AdminImmobilierReportResponse.php` + items).
 * Le contrôleur renvoie l'objet rapport directement (pas l'enveloppe
 * `Feedback`), donc `GET /v1/reports/admin-immobilier` répond le corps tel
 * quel, avec des propriétés camelCase.
 *
 * Les montants sont des chaînes décimales (`NUMERIC(12,2)`), jamais des
 * flottants : conserver `string` empêche toute perte de centimes.
 */

/** Niveau d'un élément d'occupation (town/parcelle/immeuble/mois). */
export type OccupancyLevel = 'city' | 'parcel' | 'building' | 'month';

/** Occupation d'un niveau : `occupancyRate` est en 0-100. */
export interface OccupancyItem {
    level: OccupancyLevel;
    levelUuid: string | null;
    label: string;
    totalUnits: number;
    occupiedUnits: number;
    availableUnits: number;
    occupancyRate: number;
}

/** Ligne d'impayé (bail) : montants au format décimal, devise portée par ligne. */
export interface ArrearsItem {
    leaseUuid: string;
    leaseReference: string;
    tenantName: string;
    unitLabel: string;
    amountDue: string;
    amountPaid: string;
    arrears: string;
    daysOverdue: number;
    currency: string;
}

/** Dépense agrégée par niveau : `currency` est un code ISO 4217 ("USD"/"CDF"). */
export interface ExpenseSummaryItem {
    category: string;
    level: string;
    levelLabel: string;
    count: number;
    totalAmount: string;
    currency: string;
    levelUuid: string | null;
}

/** Réponse brute de `GET /api/v1/reports/admin-immobilier`. */
export interface AdminImmobilierReport {
    organizationUuid: string;
    organizationName: string;
    periodCovered: string;
    generatedAt: string;
    occupancyByParcel: OccupancyItem[];
    occupancyByBuilding: OccupancyItem[];
    arrears: ArrearsItem[];
    propertyExpenses: ExpenseSummaryItem[];
    occupancyEvolution: OccupancyItem[];
    totalUnits: number;
    globalOccupancyRate: number;
    currency: string;
}

/** Carte indicateur pour l'en-tête du rapport. */
export interface AdminImmobilierReportsMetric {
    id: string;
    label: string;
    value: string;
    helper: string;
    tone: 'primary' | 'success' | 'warning' | 'info';
}

/** Données normalisées préparées pour l'affichage du rapport. */
export interface AdminImmobilierReportsData {
    organizationName: string;
    periodCovered: string;
    currency: string;
    metrics: AdminImmobilierReportsMetric[];
    buildings: OccupancyItem[];
    arrears: ArrearsItem[];
    expenses: ExpenseSummaryItem[];
    /** Derniers mois d'occupation (déjà bornés à 6 par le service). */
    evolution: OccupancyItem[];
}