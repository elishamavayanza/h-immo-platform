/**
 * locatairesService — Page Locataires branchée sur l'API réelle.
 *
 * Lecture : `tenants` (liste maître), `leases` (bail en cours par locataire,
 * statut `active`) et `units` (référence de l'unité occupée), en parallèle.
 * Le modèle ne porte ni solde ni statut locataire : la colonne « Fin du
 * bail » est la seule situation dérivée affichée.
 *
 * Écriture : `PATCH /api/v1/tenants/{uuid}/archive` (suppression logique +
 * audit `ARCHIVE_TENANT`, 409 si déjà archivé). Une erreur applicative est
 * levée comme `ApiError` par le client HTTP et remontée au toast.
 */
import { apiClient } from '../../../../../services/api/client';
import type { Feedback } from '../../../../../services/api/api.types';
import { REFERENCE_LIMIT, fetchBuildings, fetchCities, fetchLeases, fetchParcels, fetchTenants, fetchUnits, tenantLabel } from '../../shared/services/referenceService';
import type { BuildingItem, LeaseItem, TenantItem } from '../../shared/types/reference.types';
import type { LocataireRow, LocatairesData } from '../types/locataire.types';

export async function fetchLocataires(organizationUuid: string, cityUuid?: string): Promise<LocatairesData> {
    const [tenants, leases, units, buildings, parcels, cities] = await Promise.all([
        fetchTenants(organizationUuid, cityUuid),
        fetchLeases(organizationUuid),
        fetchUnits(organizationUuid),
        fetchBuildings(organizationUuid),
        fetchParcels(organizationUuid),
        fetchCities(organizationUuid),
    ]);

    const unitById = new Map(units.items.map((unit) => [unit.id, unit]));
    const buildingById = new Map(buildings.items.map((building: BuildingItem) => [building.id, building]));
    const parcelById = new Map(parcels.items.map((parcel) => [parcel.id, parcel]));
    const unitCityById = new Map(units.items.map((unit) => {
        const building = buildingById.get(unit.buildingId);
        const parcel = building ? parcelById.get(building.parcelId) : null;
        return [unit.id, parcel?.cityId ?? null] as const;
    }));

    // Une personne peut avoir plusieurs logements : garder chaque bail
    // courant au lieu d'écraser silencieusement les précédents dans la ligne.
    const currentLeasesByTenant = new Map<string, LeaseItem[]>();
    leases.items.forEach((lease) => {
        const leaseUnitIds = lease.unitIds?.length ? lease.unitIds : [lease.unitId];
        if (cityUuid && !leaseUnitIds.some((id) => unitCityById.get(id) === cityUuid)) return;
        if (lease.status !== 'active' && lease.status !== 'draft') return;
        const current = currentLeasesByTenant.get(lease.tenantId) ?? [];
        current.push(lease);
        currentLeasesByTenant.set(lease.tenantId, current);
        lease.occupants?.forEach((occupant) => {
            const related = currentLeasesByTenant.get(occupant.tenantId) ?? [];
            if (!related.some((item) => item.id === lease.id)) related.push(lease);
            currentLeasesByTenant.set(occupant.tenantId, related);
        });
    });

    const rows: LocataireRow[] = tenants.items.map((tenant) => {
        const tenantLeases = (currentLeasesByTenant.get(tenant.id) ?? []).sort((a, b) => {
            if (a.status !== b.status) return a.status === 'active' ? -1 : 1;
            return b.createdAt.localeCompare(a.createdAt);
        });
        const lease = tenantLeases[0] ?? null;
        const activeLease = lease?.status === 'active' ? lease : null;
        const leasedUnitIds = [...new Set(tenantLeases.flatMap((item) => item.unitIds?.length ? item.unitIds : [item.unitId]))];
        const currentUnitReferences = [...new Set(leasedUnitIds.map((id) => unitById.get(id)?.reference).filter((reference): reference is string => Boolean(reference)))];
        const currentBuildingIds = [...new Set(leasedUnitIds.map((id) => unitById.get(id)?.buildingId).filter((id): id is string => Boolean(id)))];
        const currentParcelIds = [...new Set(leasedUnitIds.map((unitId) => {
            const itemUnit = unitById.get(unitId);
            const itemBuilding = itemUnit ? buildingById.get(itemUnit.buildingId) : null;
            return itemBuilding ? parcelById.get(itemBuilding.parcelId)?.id ?? null : null;
        }).filter((id): id is string => id !== null))];

        return {
            id: tenant.id,
            cityUuid: tenant.cityId,
            name: tenantLabel(tenant),
            sublabel: [tenant.email, tenant.phone].filter(Boolean).join(' · ') || '—',
            type: tenant.type === 'company' ? 'company' : 'individual',
            phone: tenant.phone,
            email: tenant.email,
            notes: tenant.notes,
            address: tenant.address ?? '—',
            unitReference: currentUnitReferences.join(', ') || null,
            unitReferences: currentUnitReferences,
            buildingIds: currentBuildingIds,
            parcelIds: currentParcelIds,
            buildingId: lease ? unitById.get(lease.unitId)?.buildingId ?? null : null,
            parcelId: lease ? buildingById.get(unitById.get(lease.unitId)?.buildingId ?? '')?.parcelId ?? null : null,
            leaseEnd: activeLease?.endDate ?? null,
            leaseUuid: lease?.id ?? null,
            leaseStatus: lease?.status ?? null,
            latestMovement: tenant.latestMovement ?? null,
        };
    });

    const truncated = tenants.total > tenants.items.length
        || leases.total > leases.items.length
        || units.total > units.items.length;
    const note = truncated
        ? `Liste partielle : ${tenants.total} locataires, ${leases.total} baux et ${units.total} unités au total — les ${REFERENCE_LIMIT} premières entrées de chaque liste sont affichées.`
        : null;

    const activeLeaseCount = [...new Set([...currentLeasesByTenant.values()].flat().filter((lease) => lease.status === 'active').map((lease) => lease.id))].length;

    return { rows, total: tenants.total, activeLeases: activeLeaseCount, leases: leases.items, note, units: units.items.map((unit) => ({ ...unit, cityUuid: unitCityById.get(unit.id) ?? null })), buildings: buildings.items, cities: cities.items.map(({ id, name }) => ({ id, name })) };
}

/** Payload pour créer un locataire (personne physique ou morale). */
export interface CreateTenantPayload {
    organizationUuid: string;
    cityUuid?: string;
    type: 'individual' | 'company';
    firstName?: string;
    lastName?: string;
    companyName?: string;
    phone: string;
    email?: string;
    address?: string;
    notes?: string;
}

/** Payload pour modifier un locataire. */
export interface UpdateTenantPayload {
    type?: 'individual' | 'company';
    firstName?: string;
    lastName?: string;
    companyName?: string;
    phone?: string;
    email?: string | null;
    address?: string | null;
    notes?: string | null;
}

/** `POST /api/v1/tenants` — créer un locataire. */
export async function createTenant(payload: CreateTenantPayload): Promise<TenantItem> {
    const { data } = await apiClient.post<Feedback<TenantItem>>('/v1/tenants', payload);
    return data.data;
}

/** `PUT /api/v1/tenants/{uuid}` — modifier un locataire. */
export async function updateTenant(uuid: string, payload: UpdateTenantPayload): Promise<TenantItem> {
    const { data } = await apiClient.put<Feedback<TenantItem>>(`/v1/tenants/${uuid}`, payload);
    return data.data;
}

/** Payload pour créer un bail. */
export interface CreateLeasePayload {
    tenantUuid: string;
    unitUuid: string;
    additionalUnitUuids?: string[];
    occupants?: Array<{ tenantUuid: string; role: 'co_tenant' | 'occupant'; startDate: string; endDate?: string }>;
    reference: string;
    startDate: string;
    endDate?: string;
    monthlyRent: string;
    depositAmount?: string;
    currency: 'USD' | 'CDF';
    notes?: string;
}

/** `POST /api/v1/leases` — créer un bail (état DRAFT). */
export async function createLease(payload: CreateLeasePayload): Promise<unknown> {
    const { data } = await apiClient.post<Feedback<unknown>>('/v1/leases', payload);
    return data.data;
}

/** `PATCH /api/v1/leases/{uuid}/activate` — transition DRAFT → ACTIVE. */
export async function activateLease(leaseUuid: string): Promise<unknown> {
    const { data } = await apiClient.patch<Feedback<unknown>>(`/v1/leases/${leaseUuid}/activate`);
    return data.data;
}

export async function recordLeaseOccupantDeparture(leaseUuid: string, occupantUuid: string, effectiveDate: string, reason: string): Promise<void> {
    await apiClient.patch<Feedback<unknown>>(`/v1/leases/${leaseUuid}/occupants/${occupantUuid}/departure`, { effectiveDate, reason });
}

export async function addLeaseOccupant(leaseUuid: string, tenantUuid: string, role: 'co_tenant' | 'occupant', startDate: string): Promise<void> {
    await apiClient.post<Feedback<unknown>>(`/v1/leases/${leaseUuid}/occupants`, { tenantUuid, role, startDate });
}

export type MovementKind = 'move_in' | 'transfer' | 'departure_announced' | 'departure_completed' | 'temporary_leave' | 'return' | 'occupants_changed' | 'correction';
export type MovementState = 'announced' | 'planned' | 'postponed' | 'cancelled' | 'completed' | 'recorded';

export interface ResidentialMovementItem {
    uuid: string;
    type: MovementKind;
    status: MovementState;
    leaseUuid: string | null;
    nextLeaseUuid: string | null;
    previousUnitUuid: string | null;
    previousUnitReference: string | null;
    nextUnitUuid: string | null;
    nextUnitReference: string | null;
    plannedDate: string | null;
    effectiveDate: string | null;
    effectiveDateEstimated: boolean;
    reason: string | null;
    details: string | null;
    correctionReason: string | null;
    recordedAt: string;
    recordedBy: string;
}

export interface RecordResidentialMovementPayload {
    type: MovementKind;
    status: MovementState;
    leaseUuid?: string;
    nextLeaseUuid?: string;
    previousUnitUuid?: string;
    nextUnitUuid?: string;
    plannedDate?: string;
    effectiveDate?: string;
    effectiveDateEstimated?: boolean;
    reason?: string;
    details?: string;
}

export interface CorrectResidentialMovementPayload extends RecordResidentialMovementPayload {
    correctionReason: string;
}

export async function fetchResidentialMovements(tenantUuid: string): Promise<ResidentialMovementItem[]> {
    const { data } = await apiClient.get<Feedback<ResidentialMovementItem[]>>(`/v1/tenants/${tenantUuid}/movements`);
    return data.data;
}

export async function recordResidentialMovement(tenantUuid: string, payload: RecordResidentialMovementPayload): Promise<ResidentialMovementItem> {
    const { data } = await apiClient.post<Feedback<ResidentialMovementItem>>(`/v1/tenants/${tenantUuid}/movements`, payload);
    return data.data;
}

export async function correctResidentialMovement(tenantUuid: string, movementUuid: string, payload: CorrectResidentialMovementPayload): Promise<ResidentialMovementItem> {
    const { data } = await apiClient.patch<Feedback<ResidentialMovementItem>>(`/v1/tenants/${tenantUuid}/movements/${movementUuid}`, payload);
    return data.data;
}

/** Archive (suppression logique) un locataire ; lève une `ApiError` en cas d'échec. */
export async function archiveTenant(tenantUuid: string): Promise<string> {
    const { data } = await apiClient.patch<Feedback<unknown>>(`/v1/tenants/${tenantUuid}/archive`);

    return data.flushDescription ?? 'Le locataire a été archivé.';
}
