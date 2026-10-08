// ============================================================
// Service du tableau de bord SUPER_ADMIN.
// Appel API réel : GET /api/v1/reports/super-admin/dashboard
// ============================================================

import { apiClient } from '../../../../../services/api/client';
import type {
    SuperAdminDashboardData,
    KpiMetric,
    RevenuePoint,
    OrganizationSummary,
    ActivityEntry,
    SystemHealthMetric,
    CityShare,
    TrendDirection,
    KpiTone,
    HealthStatus,
    OrganizationStatus,
    ActivityKind,
    DashboardIconName,
} from '../types/superAdminDashboard.types';

function mapTrend(trend: string): TrendDirection {
    if (trend === 'up' || trend === 'down' || trend === 'flat') return trend;
    return 'flat';
}

function mapTone(tone: string): KpiTone {
    const validTones: KpiTone[] = ['primary', 'success', 'warning', 'danger', 'info'];
    return validTones.includes(tone as KpiTone) ? tone as KpiTone : 'primary';
}

function mapIcon(icon: string): DashboardIconName {
    const validIcons: DashboardIconName[] = [
        'building', 'users', 'briefcase', 'revenue', 'pulse', 'shield',
        'server', 'arrow-up', 'arrow-down', 'minus', 'sparkle', 'plus',
        'check', 'warning', 'trash', 'login',
    ];
    return validIcons.includes(icon as DashboardIconName) ? icon as DashboardIconName : 'building';
}

function mapStatus(status: string): OrganizationStatus {
    const valid: OrganizationStatus[] = ['active', 'suspended', 'inactive'];
    return valid.includes(status as OrganizationStatus) ? status as OrganizationStatus : 'active';
}

function mapHealth(status: string): HealthStatus {
    const valid: HealthStatus[] = ['healthy', 'warning', 'critical'];
    return valid.includes(status as HealthStatus) ? status as HealthStatus : 'healthy';
}

function mapKind(kind: string): ActivityKind {
    const valid: ActivityKind[] = ['create', 'update', 'delete', 'login', 'alert'];
    return valid.includes(kind as ActivityKind) ? kind as ActivityKind : 'update';
}

interface SuperAdminDashboardResponse {
    kpis: Array<{ id: string; label: string; value: string; delta: number; trend: string; positive: boolean; helper: string; tone: string; icon: string }>;
    revenueSeries: Array<{ label: string; revenue: number; subscriptions: number }>;
    recentOrganizations: Array<{ uuid: string; name: string; code: string; status: string; cityCount: number; unitCount: number; occupancyRate: number; revenues: string; expenses: string; arrears: string }>;
    activity: Array<{ id: string; actor: string; action: string; target: string; timestamp: string; kind: string }>;
    health: Array<{ id: string; label: string; value: number; unit: string; status: string }>;
    topCities: Array<{ name: string; count: number; share: number }>;
    totalOrganizations: number;
    activeOrganizations: number;
    totalUsers: number;
}

export async function fetchSuperAdminDashboard(): Promise<SuperAdminDashboardData> {
    const { data } = await apiClient.get<SuperAdminDashboardResponse>('/v1/reports/super-admin/dashboard');

    const kpis: KpiMetric[] = data.kpis.map((k) => ({
        id: k.id,
        label: k.label,
        value: k.value,
        delta: k.delta,
        trend: mapTrend(k.trend),
        positive: k.positive,
        helper: k.helper,
        tone: mapTone(k.tone),
        icon: mapIcon(k.icon),
    }));

    const revenueSeries: RevenuePoint[] = data.revenueSeries.map((r) => ({
        label: r.label,
        revenue: r.revenue,
        subscriptions: r.subscriptions,
    }));

    const recentOrganizations: OrganizationSummary[] = data.recentOrganizations.map((o) => ({
        id: o.uuid,
        name: o.name,
        slug: o.code.toLowerCase(),
        city: '',
        plan: o.status === 'ACTIVE' ? 'Pro' : 'Starter',
        users: 0,
        properties: o.unitCount,
        status: mapStatus(o.status.toLowerCase()),
        createdAt: '',
    }));

    const activity: ActivityEntry[] = data.activity.map((a) => ({
        id: a.id,
        actor: a.actor,
        action: a.action,
        target: a.target,
        timestamp: a.timestamp,
        kind: mapKind(a.kind),
    }));

    const health: SystemHealthMetric[] = data.health.map((h) => ({
        id: h.id,
        label: h.label,
        value: h.value,
        unit: h.unit,
        status: mapHealth(h.status),
    }));

    const topCities: CityShare[] = data.topCities.map((c) => ({
        name: c.name,
        count: c.count,
        share: c.share,
    }));

    return {
        kpis,
        revenueSeries,
        recentOrganizations,
        activity,
        health,
        topCities,
    };
}