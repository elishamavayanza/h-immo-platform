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
import { REFERENCE_LIMIT, fetchBuildings, fetchLeases, fetchTenants, fetchUnits, tenantLabel } from '../../shared/services/referenceService';
import type { BuildingItem, LeaseItem, TenantItem } from '../../shared/types/reference.types';
import type { LocataireRow, LocatairesData } from '../types/locataire.types';

export async function fetchLocataires(organizationUuid: string): Promise<LocatairesData> {
    const [tenants, leases, units, buildings] = await Promise.all([
        fetchTenants(organizationUuid),
        fetchLeases(organizationUuid),
        fetchUnits(organizationUuid),
        fetchBuildings(organizationUuid),
    ]);

    const unitById = new Map(units.items.map((unit) => [unit.id, unit]));
    const buildingById = new Map(buildings.items.map((building: BuildingItem) => [building.id, building]));

    // Un locataire ne devrait avoir qu'un bail actif (contrôle métier en
    // PHP) : au cas où, on garde la fin de bail la plus lointaine.
    const currentLeaseByTenant = new Map<string, LeaseItem>();
    leases.items.forEach((lease) => {
        if (lease.status !== 'active' && lease.status !== 'draft') return;
        const existing = currentLeaseByTenant.get(lease.tenantId);
        const preferred = lease.status === 'active' || existing?.status !== 'active';
        if (preferred && (!existing || lease.createdAt >= existing.createdAt)) currentLeaseByTenant.set(lease.tenantId, lease);
    });

    const rows: LocataireRow[] = tenants.items.map((tenant) => {
        const lease = currentLeaseByTenant.get(tenant.id) ?? null;
        const activeLease = lease?.status === 'active' ? lease : null;

        return {
            id: tenant.id,
            name: tenantLabel(tenant),
            sublabel: [tenant.email, tenant.phone].filter(Boolean).join(' · ') || '—',
            type: tenant.type === 'company' ? 'company' : 'individual',
            phone: tenant.phone,
            address: tenant.address ?? '—',
            unitReference: lease ? unitById.get(lease.unitId)?.reference ?? null : null,
            buildingId: lease ? unitById.get(lease.unitId)?.buildingId ?? null : null,
            parcelId: lease ? buildingById.get(unitById.get(lease.unitId)?.buildingId ?? '')?.parcelId ?? null : null,
            leaseEnd: activeLease?.endDate ?? null,
            leaseUuid: lease?.id ?? null,
            leaseStatus: lease?.status ?? null,
        };
    });

    const truncated = tenants.total > tenants.items.length
        || leases.total > leases.items.length
        || units.total > units.items.length;
    const note = truncated
        ? `Liste partielle : ${tenants.total} locataires, ${leases.total} baux et ${units.total} unités au total — les ${REFERENCE_LIMIT} premières entrées de chaque liste sont affichées.`
        : null;

    const activeTenantIds = new Set(leases.items.filter((lease) => lease.status === 'active').map((lease) => lease.tenantId));

    return { rows, total: tenants.total, activeLeases: activeTenantIds.size, note, units: units.items, buildings: buildings.items };
}

/** Payload pour créer un locataire (personne physique ou morale). */
export interface CreateTenantPayload {
    organizationUuid: string;
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
    type: 'individual' | 'company';
    firstName?: string;
    lastName?: string;
    companyName?: string;
    phone: string;
    email?: string;
    address?: string;
    notes?: string;
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

/** Archive (suppression logique) un locataire ; lève une `ApiError` en cas d'échec. */
export async function archiveTenant(tenantUuid: string): Promise<string> {
    const { data } = await apiClient.patch<Feedback<unknown>>(`/v1/tenants/${tenantUuid}/archive`);

    return data.flushDescription ?? 'Le locataire a été archivé.';
}
