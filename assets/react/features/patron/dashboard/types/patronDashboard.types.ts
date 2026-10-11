/**
 * patronDashboard.types — Tableau de bord PATRON
 *
 * Le dashboard n'a pas d'endpoint dédié : il projette le rapport
 * `/v1/reports/patron` en indicateurs synthétiques, exactement comme le
 * tableau de bord ADMIN_IMMOBILIER projette son propre rapport. Les types
 * bruts proviennent du contrat `../../reports/types`.
 */
import type { ArrearsItem, ExpenseSummaryItem, OccupancyItem } from '../../reports/types';

/** Carte indicateur du tableau de bord. */
export interface PatronDashboardMetric {
    id: string;
    label: string;
    value: string;
    detail: string;
    tone: 'primary' | 'success' | 'warning' | 'info';
}

/** Données normalisées du tableau de bord. */
export interface PatronDashboardData {
    organizationName: string;
    periodCovered: string;
    currency: string;
    totalUnits: number;
    globalOccupancyRate: number;
    /** 4 indicateurs synthétiques (revenus, dépenses, impayés, occupation). */
    metrics: PatronDashboardMetric[];
    /** Occupation par ville (barres de progression). */
    cities: OccupancyItem[];
    /** Impayés à recouvrer, triés par retard côté backend. */
    arrears: ArrearsItem[];
    /** Dépenses agrégées par ville. */
    expenses: ExpenseSummaryItem[];
}
