import assert from 'node:assert/strict';

import { filterTenantsByHousing } from '../assets/react/features/admin_immobilier/locataires/utils/tenantFilters.ts';
import type { LeaseItem } from '../assets/react/features/admin_immobilier/shared/types/reference.types.ts';

const today = '2026-10-16';
const lease = (overrides: Partial<LeaseItem> = {}): LeaseItem => ({
    id: 'lease-a', organizationId: 'org-a', tenantId: 'tenant-a', unitId: 'unit-a', unitIds: ['unit-a'], occupants: [],
    reference: 'BAIL-A', startDate: '2026-01-01', endDate: null, depositAmount: null, monthlyRent: '100.00',
    exchangeRate: null, referenceCurrency: null, currency: 'USD', status: 'active', terminationDate: null,
    terminationReason: null, notes: null, terms: null, createdAt: today, updatedAt: today, ...overrides,
});
const tenants = [
    { id: 'tenant-a' }, { id: 'tenant-b' }, { id: 'tenant-c' }, { id: 'tenant-d' },
    { id: 'tenant-e', latestMovement: { type: 'departure_announced', status: 'announced', plannedDate: '2026-10-20', effectiveDate: null, recordedAt: '2026-10-15T10:00:00+00:00' } },
    { id: 'tenant-f', latestMovement: { type: 'move_in', status: 'completed', plannedDate: null, effectiveDate: today, recordedAt: '2026-10-10T10:00:00+00:00' } },
    { id: 'tenant-g', latestMovement: { type: 'departure_completed', status: 'completed', plannedDate: null, effectiveDate: '2026-10-12', recordedAt: '2026-10-16T10:00:00+00:00' } },
];
const leases = [
    lease({ id: 'lease-a', endDate: '2026-11-01' }),
    lease({ id: 'lease-shared', tenantId: 'other', unitId: 'unit-b', unitIds: ['unit-b'], occupants: [{ id: 'presence-b', tenantId: 'tenant-b', role: 'occupant', startDate: '2026-01-01', endDate: null }] }),
    lease({ id: 'lease-multi', tenantId: 'tenant-c', unitId: 'unit-c', unitIds: ['unit-c', 'unit-d'] }),
    lease({ id: 'lease-former', tenantId: 'tenant-d', status: 'terminated' }),
    lease({ id: 'lease-future', tenantId: 'tenant-d', startDate: '2026-11-01' }),
    lease({ id: 'lease-e', tenantId: 'tenant-e' }),
    lease({ id: 'lease-f', tenantId: 'tenant-f' }),
    lease({ id: 'lease-g', tenantId: 'tenant-g' }),
];

assert.deepEqual(filterTenantsByHousing(tenants, leases, 'currently_housed', today).map(({ id }) => id), ['tenant-a', 'tenant-b', 'tenant-c', 'tenant-e', 'tenant-f', 'tenant-g']);
assert.deepEqual(filterTenantsByHousing(tenants, leases, 'former', today).map(({ id }) => id), ['tenant-d']);
assert.deepEqual(filterTenantsByHousing(tenants, [...leases,
    lease({ id: 'lease-multi-home-2', tenantId: 'tenant-c', unitId: 'unit-e', unitIds: ['unit-e'] }),
], 'multiple_homes', today).map(({ id }) => id), ['tenant-c']);
assert.deepEqual(filterTenantsByHousing(tenants, leases, 'multiple_homes', today), [], 'un seul bail composé ne doit pas être compté comme plusieurs locations');
assert.deepEqual(filterTenantsByHousing(tenants, leases, 'expiring_soon', today).map(({ id }) => id), ['tenant-a']);
assert.equal(filterTenantsByHousing(tenants, leases, 'all', today).length, tenants.length);
assert.deepEqual(filterTenantsByHousing(tenants, leases, 'departure_pending', today).map(({ id }) => id), ['tenant-e']);
assert.deepEqual(filterTenantsByHousing(tenants, leases, 'recent_movement', today).map(({ id }) => id), ['tenant-e', 'tenant-f', 'tenant-g']);
assert.deepEqual(filterTenantsByHousing(tenants, leases, 'late_movement', today).map(({ id }) => id), ['tenant-g']);

console.log('9 contrôles des filtres locataires, 0 échec.');
