// ============================================================
// Types du tableau de bord SUPER_ADMIN (vue plateforme).
// Aucune dépendance API : ce sont les formes consommées par les
// composants et par le service mocké.
// ============================================================

export type TrendDirection = 'up' | 'down' | 'flat';
export type KpiTone = 'primary' | 'success' | 'warning' | 'danger' | 'info';
export type HealthStatus = 'healthy' | 'warning' | 'critical';
export type OrganizationPlan = 'Starter' | 'Pro' | 'Enterprise';
export type OrganizationStatus = 'active' | 'trial' | 'suspended';
export type ActivityKind = 'create' | 'update' | 'delete' | 'login' | 'alert';

export interface KpiMetric {
    id: string;
    label: string;
    value: string;
    delta: number;
    trend: TrendDirection;
    /** `true` si l'évolution est favorable (baisse du churn = good). */
    positive: boolean;
    helper: string;
    tone: KpiTone;
    icon: DashboardIconName;
}

export interface RevenuePoint {
    label: string;
    revenue: number;
    subscriptions: number;
}

export interface OrganizationSummary {
    id: string;
    name: string;
    slug: string;
    city: string;
    plan: OrganizationPlan;
    users: number;
    properties: number;
    status: OrganizationStatus;
    createdAt: string;
}

export interface ActivityEntry {
    id: string;
    actor: string;
    action: string;
    target: string;
    timestamp: string;
    kind: ActivityKind;
}

export interface SystemHealthMetric {
    id: string;
    label: string;
    value: number;
    unit: string;
    status: HealthStatus;
}

export interface CityShare {
    name: string;
    count: number;
    share: number;
}

export interface SuperAdminDashboardData {
    kpis: KpiMetric[];
    revenueSeries: RevenuePoint[];
    recentOrganizations: OrganizationSummary[];
    activity: ActivityEntry[];
    health: SystemHealthMetric[];
    topCities: CityShare[];
}

/** Noms d'icônes disponibles (résolues par `DashboardIcon`). */
export type DashboardIconName =
    | 'building'
    | 'users'
    | 'briefcase'
    | 'revenue'
    | 'pulse'
    | 'shield'
    | 'server'
    | 'arrow-up'
    | 'arrow-down'
    | 'minus'
    | 'sparkle'
    | 'plus'
    | 'check'
    | 'warning'
    | 'trash'
    | 'login';
