/**
 * loyersService — Page Loyers branchée sur l'API réelle.
 *
 * Lecture : `rents` (filtré par statut calculé côté serveur, `dueDate` DESC)
 * joint côté client avec les baux, locataires, unités et immeubles pour les
 * libellés — `RentResponse` n'exposant que `leaseId`.
 *
 * Le filtre de statut est serveur (le backend traduit `overdue` en condition
 * dérivée, il n'est jamais persisté) ; la recherche texte reste locale sur
 * le jeu chargé, faute de `search` sur l'endpoint.
 */
import { REFERENCE_LIMIT, fetchBuildings, fetchLeases, fetchRents, fetchTenants, fetchUnits, tenantLabel } from '../../shared/services/referenceService';
import type { RentStatusCode } from '../types/loyer.types';
import type { LoyerRow, LoyersData } from '../types/loyer.types';

const RENT_STATUSES: RentStatusCode[] = ['pending', 'partially_paid', 'paid', 'overdue'];

/** `period` (DATE du 1er du mois) → « Octobre 2026 », sans dérive de fuseau. */
function periodLabel(period: string): string {
    const match = /^(\d{4})-(\d{2})/.exec(period);
    if (!match) return period;

    const date = new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, 1));
    const label = new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(date);

    return label.charAt(0).toUpperCase() + label.slice(1);
}

export async function fetchLoyers(organizationUuid: string, status: string): Promise<LoyersData> {
    const [rents, leases, tenants, units, buildings] = await Promise.all([
        fetchRents(organizationUuid, status),
        fetchLeases(organizationUuid),
        fetchTenants(organizationUuid),
        fetchUnits(organizationUuid),
        fetchBuildings(organizationUuid),
    ]);

    const leaseById = new Map(leases.items.map((lease) => [lease.id, lease]));
    const tenantById = new Map(tenants.items.map((tenant) => [tenant.id, tenant]));
    const unitById = new Map(units.items.map((unit) => [unit.id, unit]));
    const buildingById = new Map(buildings.items.map((building) => [building.id, building]));

    const rows: LoyerRow[] = rents.items.map((rent) => {
        const lease = leaseById.get(rent.leaseId);
        const tenant = lease ? tenantById.get(lease.tenantId) : undefined;
        const unit = lease ? unitById.get(lease.unitId) : undefined;
        const building = unit ? buildingById.get(unit.buildingId) : undefined;

        const unitLabel = unit
            ? [building?.name, unit.reference].filter(Boolean).join(' · ')
            : '—';

        return {
            id: rent.id,
            tenant: tenant ? tenantLabel(tenant) : '—',
            unitLabel,
            periodLabel: periodLabel(rent.period),
            dueDate: rent.dueDate,
            amount: rent.amount,
            currency: rent.currency,
            status: RENT_STATUSES.includes(rent.status as RentStatusCode)
                ? rent.status as RentStatusCode
                : 'pending',
        };
    });

    const truncated = rents.total > rents.items.length
        || tenants.total > tenants.items.length
        || leases.total > leases.items.length;
    const note = truncated
        ? `Liste partielle : ${rents.total} échéances au total, les ${REFERENCE_LIMIT} plus récentes sont affichées.`
        : null;

    return { rows, total: rents.total, note };
}
