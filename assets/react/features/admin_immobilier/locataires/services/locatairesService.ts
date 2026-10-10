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
import { REFERENCE_LIMIT, fetchLeases, fetchTenants, fetchUnits, tenantLabel } from '../../shared/services/referenceService';
import type { LeaseItem } from '../../shared/types/reference.types';
import type { LocataireRow, LocatairesData } from '../types/locataire.types';

export async function fetchLocataires(organizationUuid: string): Promise<LocatairesData> {
    const [tenants, leases, units] = await Promise.all([
        fetchTenants(organizationUuid),
        fetchLeases(organizationUuid),
        fetchUnits(organizationUuid),
    ]);

    const unitById = new Map(units.items.map((unit) => [unit.id, unit]));

    // Un locataire ne devrait avoir qu'un bail actif (contrôle métier en
    // PHP) : au cas où, on garde la fin de bail la plus lointaine.
    const activeLeaseByTenant = new Map<string, LeaseItem>();
    leases.items.forEach((lease) => {
        if (lease.status !== 'active') return;
        const existing = activeLeaseByTenant.get(lease.tenantId);
        if (!existing || (lease.endDate ?? '') > (existing.endDate ?? '')) {
            activeLeaseByTenant.set(lease.tenantId, lease);
        }
    });

    const rows: LocataireRow[] = tenants.items.map((tenant) => {
        const lease = activeLeaseByTenant.get(tenant.id) ?? null;

        return {
            id: tenant.id,
            name: tenantLabel(tenant),
            sublabel: [tenant.email, tenant.phone].filter(Boolean).join(' · ') || '—',
            type: tenant.type === 'company' ? 'company' : 'individual',
            phone: tenant.phone,
            address: tenant.address ?? '—',
            unitReference: lease ? unitById.get(lease.unitId)?.reference ?? null : null,
            leaseEnd: lease?.endDate ?? null,
        };
    });

    const truncated = tenants.total > tenants.items.length
        || leases.total > leases.items.length
        || units.total > units.items.length;
    const note = truncated
        ? `Liste partielle : ${tenants.total} locataires, ${leases.total} baux et ${units.total} unités au total — les ${REFERENCE_LIMIT} premières entrées de chaque liste sont affichées.`
        : null;

    return { rows, total: tenants.total, activeLeases: activeLeaseByTenant.size, note };
}

/** Archive (suppression logique) un locataire ; lève une `ApiError` en cas d'échec. */
export async function archiveTenant(tenantUuid: string): Promise<string> {
    const { data } = await apiClient.patch<Feedback<unknown>>(`/v1/tenants/${tenantUuid}/archive`);

    return data.flushDescription ?? 'Le locataire a été archivé.';
}
