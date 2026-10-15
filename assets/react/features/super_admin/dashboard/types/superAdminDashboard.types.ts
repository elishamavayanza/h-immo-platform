export type DashboardStatusTone = 'success' | 'warning' | 'muted';
export type DashboardActivityKind = 'create' | 'update' | 'alert';

export interface PlatformMetricStatus {
    label: string;
    value: number;
    tone: DashboardStatusTone;
}

export interface PlatformMetric {
    total: number;
    statuses: PlatformMetricStatus[];
}

export interface PlatformActivityEntry {
    id: string;
    action: string;
    timestamp: string;
    kind: DashboardActivityKind;
}

export interface SuperAdminDashboardData {
    generatedAt: string;
    organizations: PlatformMetric;
    users: PlatformMetric;
    activity: PlatformActivityEntry[];
}

export type DashboardIconName =
    | 'building' | 'users' | 'briefcase' | 'revenue' | 'pulse' | 'shield' | 'server'
    | 'arrow-up' | 'arrow-down' | 'minus' | 'sparkle' | 'plus' | 'check' | 'warning'
    | 'trash' | 'login' | 'refresh';
