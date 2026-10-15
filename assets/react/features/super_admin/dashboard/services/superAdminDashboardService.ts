import { apiClient } from '../../../../../services/api/client';
import type {
    DashboardActivityKind,
    DashboardStatusTone,
    PlatformActivityEntry,
    SuperAdminDashboardData,
} from '../types/superAdminDashboard.types';

interface SuperAdminDashboardResponse {
    generatedAt: string;
    totalOrganizations: number;
    activeOrganizations: number;
    suspendedOrganizations: number;
    inactiveOrganizations: number;
    totalUsers: number;
    activeUsers: number;
    disabledUsers: number;
    activity: Array<{ id: string; action: string; timestamp: string; kind: string }>;
}

function mapActivityKind(kind: string): DashboardActivityKind {
    return kind === 'create' || kind === 'alert' || kind === 'update' ? kind : 'update';
}

function asCount(value: number): number {
    return Number.isFinite(value) && value >= 0 ? Math.trunc(value) : 0;
}

function mapActivity(entry: SuperAdminDashboardResponse['activity'][number]): PlatformActivityEntry {
    return {
        id: entry.id,
        action: entry.action,
        timestamp: entry.timestamp,
        kind: mapActivityKind(entry.kind),
    };
}

export async function fetchSuperAdminDashboard(): Promise<SuperAdminDashboardData> {
    const { data } = await apiClient.get<SuperAdminDashboardResponse>('/v1/reports/super-admin/dashboard');

    return {
        generatedAt: data.generatedAt,
        organizations: {
            total: asCount(data.totalOrganizations),
            statuses: [
                { label: 'Actives', value: asCount(data.activeOrganizations), tone: 'success' as DashboardStatusTone },
                { label: 'Suspendues', value: asCount(data.suspendedOrganizations), tone: 'warning' as DashboardStatusTone },
                { label: 'Inactives', value: asCount(data.inactiveOrganizations), tone: 'muted' as DashboardStatusTone },
            ],
        },
        users: {
            total: asCount(data.totalUsers),
            statuses: [
                { label: 'Actifs', value: asCount(data.activeUsers), tone: 'success' as DashboardStatusTone },
                { label: 'Désactivés', value: asCount(data.disabledUsers), tone: 'warning' as DashboardStatusTone },
            ],
        },
        activity: data.activity.map(mapActivity),
    };
}
