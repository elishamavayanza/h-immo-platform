/**
 * Types du rapport ADMIN_VILLE — miroirs de `AdminVilleReportResponse` et de
 * ses items (`src/Dto/Response/Report/`). L'endpoint
 * `GET /api/v1/reports/admin-ville/{cityUuid}` renvoie directement le rapport
 * (sans enveloppe `Feedback`).
 */

export type OccupancyLevel = 'city' | 'parcel' | 'building' | 'month';

export interface OccupancyItem {
    level: OccupancyLevel;
    levelUuid: string | null;
    label: string;
    totalUnits: number;
    occupiedUnits: number;
    availableUnits: number;
    occupancyRate: number;
}

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

export interface ExpenseSummaryItem {
    category: string;
    level: string;
    levelLabel: string;
    count: number;
    totalAmount: string;
    currency: string;
    levelUuid: string | null;
}

export interface WorkerActivityItem {
    workerUuid: string;
    fullName: string;
    role: string;
    assignmentLabel: string;
    monthlySalary: string;
    currency: string;
}

/** `AdminVilleReportResponse` — `GET /api/v1/reports/admin-ville/{cityUuid}`. */
export interface AdminVilleReport {
    cityUuid: string;
    cityName: string;
    periodCovered: string;
    generatedAt: string;
    occupancyByParcel: OccupancyItem[];
    occupancyByBuilding: OccupancyItem[];
    arrears: ArrearsItem[];
    cityExpenses: ExpenseSummaryItem[];
    workers: WorkerActivityItem[];
    totalUnits: number;
    occupancyRate: number;
    totalExpenses: string;
    currency: string;
}

/** KPI dérivé du rapport (le backend ne renvoie pas de tableau de KPI). */
export interface AdminVilleReportsMetric {
    id: string;
    label: string;
    value: string;
    helper: string;
    tone: 'primary' | 'success' | 'warning' | 'info';
}

/** Modèle de vue normalisé consommé par les composants. */
export interface AdminVilleReportsData {
    cityName: string;
    periodCovered: string;
    currency: string;
    metrics: AdminVilleReportsMetric[];
    buildings: OccupancyItem[];
    parcels: OccupancyItem[];
    arrears: ArrearsItem[];
    expenses: ExpenseSummaryItem[];
    workers: WorkerActivityItem[];
}