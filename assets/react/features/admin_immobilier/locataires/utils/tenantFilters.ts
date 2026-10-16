import type { LeaseItem } from '../../shared/types/reference.types';

export type TenantHousingFilter = 'all' | 'currently_housed' | 'former' | 'multiple_homes' | 'expiring_soon' | 'departure_pending' | 'recent_movement' | 'late_movement';
type MovementSummary = { type: string; status: string; plannedDate: string | null; effectiveDate: string | null; recordedAt: string };

function isWithinOccupancy(lease: LeaseItem, today: string): boolean {
    return lease.startDate <= today && (lease.endDate === null || lease.endDate >= today);
}

export function filterTenantsByHousing<T extends { id: string; latestMovement?: MovementSummary | null }>(
    rows: T[],
    leases: LeaseItem[],
    filter: TenantHousingFilter,
    today: string,
): T[] {
    if (filter === 'all') return rows;

    const cutoff = new Date(`${today}T00:00:00Z`);
    cutoff.setUTCDate(cutoff.getUTCDate() + 30);
    const cutoffDate = cutoff.toISOString().slice(0, 10);
    const recentCutoff = new Date(`${today}T00:00:00Z`);
    recentCutoff.setUTCDate(recentCutoff.getUTCDate() - 30);
    const recentCutoffDate = recentCutoff.toISOString().slice(0, 10);

    return rows.filter((tenant) => {
        const activeLeases = leases.filter((lease) => lease.status === 'active' && isWithinOccupancy(lease, today)
            && (lease.tenantId === tenant.id || lease.occupants.some((occupant) => occupant.tenantId === tenant.id
                && occupant.startDate <= today && (occupant.endDate === null || occupant.endDate >= today))));

        switch (filter) {
            case 'currently_housed':
                return activeLeases.length > 0;
            case 'former':
                return activeLeases.length === 0;
            case 'multiple_homes':
                return activeLeases.length > 1;
            case 'expiring_soon':
                return activeLeases.some((lease) => lease.endDate !== null && lease.endDate >= today && lease.endDate <= cutoffDate);
            case 'departure_pending':
                return tenant.latestMovement?.type === 'departure_announced'
                    && ['announced', 'planned', 'postponed'].includes(tenant.latestMovement.status);
            case 'recent_movement':
                return Boolean(tenant.latestMovement && tenant.latestMovement.recordedAt.slice(0, 10) >= recentCutoffDate);
            case 'late_movement':
                return Boolean(tenant.latestMovement?.status === 'completed'
                    && tenant.latestMovement.effectiveDate
                    && tenant.latestMovement.effectiveDate < tenant.latestMovement.recordedAt.slice(0, 10));
        }
    });
}
