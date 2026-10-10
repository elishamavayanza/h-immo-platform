/**
 * referenceService — Lectures des listes de référence (villes, parcelles,
 * immeubles, unités, locataires, baux) partagées par les pages
 * ADMIN_IMMOBILIER.
 *
 * Contrat : chaque route répond par l'enveloppe `Feedback`, dont `data`
 * contient le corps paginé `{items, total, …}`. Le client HTTP a un
 * `baseURL = '/api'`, donc les url commencent par `/v1/…`.
 *
 * Pagination : les listes sont bornées à `REFERENCE_LIMIT` (le maximum des
 * `#[Assert\Range]` des DTO de filtre). Au-delà, la page affiche une note de
 * troncature plutôt que de faire croire à une liste complète ; le tri et le
 * filtrage s'effectuent ensuite côté client sur le jeu chargé. Le périmètre
 * multi-organisation et le scope `ADMIN_VILLE` sont appliqués côté backend :
 * aucune sélection locale par UUID n'est nécessaire.
 */
import { apiClient } from '../../../../../services/api/client';
import type { Feedback } from '../../../../../services/api/api.types';
import type {
    BuildingItem,
    CityItem,
    ExpenseItem,
    LeaseItem,
    ListPage,
    ParcelItem,
    PropertyCatalog,
    RentItem,
    TenantItem,
    UnitItem,
    WorkerAssignmentItem,
    WorkerItem,
} from '../types/reference.types';

/** Borne unique des listes : `limit` max des DTO de filtre backend. */
export const REFERENCE_LIMIT = 100;

async function getList<T>(
    url: string,
    organizationId: string,
    extraParams: Record<string, string> = {},
): Promise<ListPage<T>> {
    const { data } = await apiClient.get<Feedback<ListPage<T>>>(url, {
        params: { organizationId, limit: REFERENCE_LIMIT, ...extraParams },
    });

    return data.data;
}

export function fetchCities(organizationId: string): Promise<ListPage<CityItem>> {
    return getList<CityItem>('/v1/property/cities', organizationId);
}

export function fetchParcels(organizationId: string): Promise<ListPage<ParcelItem>> {
    return getList<ParcelItem>('/v1/parcels', organizationId);
}

export function fetchBuildings(organizationId: string): Promise<ListPage<BuildingItem>> {
    return getList<BuildingItem>('/v1/property/buildings', organizationId);
}

export function fetchUnits(organizationId: string): Promise<ListPage<UnitItem>> {
    return getList<UnitItem>('/v1/units', organizationId);
}

export function fetchTenants(organizationId: string): Promise<ListPage<TenantItem>> {
    return getList<TenantItem>('/v1/tenants', organizationId);
}

/** Baux de l'organisation, triés `startDate` DESC par défaut côté backend. */
export function fetchLeases(organizationId: string): Promise<ListPage<LeaseItem>> {
    return getList<LeaseItem>('/v1/leases', organizationId);
}

/**
 * Échéances de loyer, `dueDate` DESC (les plus récentes d'abord).
 * `status` est le statut calculé backend (`pending`, `partially_paid`,
 * `paid`, `overdue`) : un filtre est préférable à un tri client, `overdue`
 * n'étant jamais persisté.
 */
export function fetchRents(organizationId: string, status?: string): Promise<ListPage<RentItem>> {
    return getList<RentItem>('/v1/rents', organizationId, {
        ...(status && status !== 'all' ? { status } : {}),
        sortBy: 'dueDate',
        sortOrder: 'DESC',
    });
}

/** Catalogue patrimonial des 4 niveaux, chargé en une seule volée parallèle. */
export async function fetchPropertyCatalog(organizationId: string): Promise<PropertyCatalog> {
    const [cities, parcels, buildings, units] = await Promise.all([
        fetchCities(organizationId),
        fetchParcels(organizationId),
        fetchBuildings(organizationId),
        fetchUnits(organizationId),
    ]);

    return { cities, parcels, buildings, units };
}

/** Libellé d'affichage d'un locataire (personne physique ou morale). */
export function tenantLabel(tenant: TenantItem): string {
    return tenant.fullName?.trim() || tenant.companyName?.trim() || '—';
}

/**
 * Dépenses de l'organisation, `expenseDate` DESC.
 *
 * `ExpenseFilterDto` n'expose pas d'`organizationId` : le périmètre est
 * appliqué côté serveur à partir des organisations et villes accessibles de
 * l'appelant. Passer un `organizationId` inconnu du DTO serait inopérant.
 */
export function fetchExpenses(): Promise<ListPage<ExpenseItem>> {
    return apiClient
        .get<Feedback<ListPage<ExpenseItem>>>('/v1/expenses', {
            params: { limit: REFERENCE_LIMIT, sortBy: 'expenseDate', sortOrder: 'DESC' },
        })
        .then((response) => response.data.data);
}

/** Travailleurs de l'organisation, pour les libellés d'affectation. */
export function fetchWorkers(organizationId: string): Promise<ListPage<WorkerItem>> {
    return getList<WorkerItem>('/v1/workers', organizationId);
}

/**
 * Affectations de personnel (`startDate` DESC).
 *
 * `WorkerAssignmentFilterDto` n'expose pas d'`organizationId` : le périmètre
 * est appliqué côté serveur (villes des organisations accessibles).
 */
export function fetchWorkerAssignments(): Promise<ListPage<WorkerAssignmentItem>> {
    return apiClient
        .get<Feedback<ListPage<WorkerAssignmentItem>>>('/v1/worker-assignments', {
            params: { limit: REFERENCE_LIMIT, sortBy: 'startDate', sortOrder: 'DESC' },
        })
        .then((response) => response.data.data);
}
