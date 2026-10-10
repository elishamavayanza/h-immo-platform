/**
 * adminImmobilierDashboard.types — Tableau de bord ADMIN_IMMOBILIER
 *
 * Le dashboard n'a pas d'endpoint dédié (seul le SUPER_ADMIN en a un) :
 * il affiche le même rapport que la page Rapports, projeté en indicateurs
 * d'activité quotidienne. Les types bruts proviennent donc du contrat
 * `/v1/reports/admin-immobilier` défini dans `../../reports/types`.
 */
import type { ArrearsItem, ExpenseSummaryItem, OccupancyItem } from '../../reports/types';

/** Carte indicateur du tableau de bord. */
export interface AdminImmobilierMetric {
    id: string;
    label: string;
    value: string;
    detail: string;
    tone: 'primary' | 'success' | 'warning' | 'info';
}

/** Données normalisées du tableau de bord. */
export interface AdminImmobilierDashboardData {
    organizationName: string;
    periodCovered: string;
    currency: string;
    totalUnits: number;
    globalOccupancyRate: number;
    /** 4 indicateurs synthétiques (unités, occupation, impayés, dépenses). */
    metrics: AdminImmobilierMetric[];
    /** Occupation par immeuble (barres de progression). */
    buildings: OccupancyItem[];
    /** Impayés à traiter, triés par retard côté backend. */
    arrears: ArrearsItem[];
    /** Dépenses agrégées par niveau du patrimoine. */
    expenses: ExpenseSummaryItem[];
}