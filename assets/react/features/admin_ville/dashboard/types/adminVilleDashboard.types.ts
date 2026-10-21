/**
 * adminVilleDashboard.types — Tableau de bord ADMIN_VILLE
 *
 * Le tableau de bord n'a pas d'endpoint dédié : il projette le même rapport
 * que la page Rapports (`GET /v1/reports/admin-ville/{cityUuid}`) en
 * indicateurs d'activité. Les types bruts proviennent donc du contrat de
 * `../../reports/types`.
 */
import type { ArrearsItem, ExpenseSummaryItem, LeaseExpiryItem, OccupancyItem } from '../../reports/types';

/** Carte indicateur du tableau de bord. */
export interface AdminVilleMetric {
    id: string;
    label: string;
    value: string;
    detail: string;
    tone: 'primary' | 'success' | 'warning' | 'info';
}

/** Données normalisées du tableau de bord d'une ville. */
export interface AdminVilleDashboardData {
    cityName: string;
    periodCovered: string;
    currency: string;
    totalUnits: number;
    occupancyRate: number;
    /** 4 indicateurs synthétiques (unités, occupation, impayés, dépenses). */
    metrics: AdminVilleMetric[];
    /** Occupation par immeuble (taux et compteurs d’unités). */
    buildings: OccupancyItem[];
    /** Impayés à traiter, triés par retard côté backend. */
    arrears: ArrearsItem[];
    upcomingLeaseExpirations: LeaseExpiryItem[];
    /** Dépenses agrégées par niveau du patrimoine. */
    expenses: ExpenseSummaryItem[];
}
