/**
 * useUnitContext — Informations de location d'une unité, chargées à la demande.
 *
 * Le panneau Patrimoine ne charge le bail, le locataire et les échéances que
 * lorsqu'une unité est sélectionnée, et uniquement via des lectures filtrées
 * côté serveur (`leases?unitId=`, `rents?leaseUuid=`, `tenants/{uuid}`) : les
 * données d'une autre organisation ou hors périmètre `UserCity` sont refusées
 * par l'API, jamais filtrées a posteriori dans le navigateur.
 *
 * Dégradation : une permission manquante (`canDo`) court-circuite l'appel et
 * marque la donnée « interdite » ; une lecture autorisée qui échoue marque la
 * donnée « indisponible » sans faire échouer les autres. Aucune valeur n'est
 * inventée : ce que l'API ne renvoie pas reste vide.
 */
import { useEffect, useState } from 'react';

import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { ApiError } from '../../../../../services/api/api.types';
import { canDo } from '../../../shared/permissions';
import { REFERENCE_LIMIT, fetchLeasesByUnit, fetchRentsByLease, fetchTenantByUuid } from '../../shared/services/referenceService';
import type { LeaseItem, RentItem, TenantItem } from '../../shared/types/reference.types';
import { pickActiveLease } from '../components/patrimoineView';

export interface UnitContext {
    isLoading: boolean;
    error: string | null;
    /** Permission de lecture absente pour cette donnée. */
    leaseForbidden: boolean;
    tenantForbidden: boolean;
    rentsForbidden: boolean;
    activeLease: LeaseItem | null;
    tenant: TenantItem | null;
    rents: RentItem[];
    /** La liste d'échéances reçue est bornée (pagination API). */
    rentsTruncated: boolean;
}

const EMPTY: UnitContext = {
    isLoading: false,
    error: null,
    leaseForbidden: false,
    tenantForbidden: false,
    rentsForbidden: false,
    activeLease: null,
    tenant: null,
    rents: [],
    rentsTruncated: false,
};

/** Seul un refus explicite (403) est « interdit » ; une panne reste une absence. */
function isForbidden(cause: unknown): boolean {
    return cause instanceof ApiError && cause.status === 403;
}

export function useUnitContext(unitId: string | null): UnitContext {
    const { currentOrganization, organizationRole } = useOrganization();
    const organizationUuid = currentOrganization?.uuid ?? null;
    const [state, setState] = useState<UnitContext>(EMPTY);

    const canViewLease = canDo(organizationRole, 'view_lease');
    const canViewTenant = canDo(organizationRole, 'view_tenant');
    const canViewRent = canDo(organizationRole, 'view_rent');

    useEffect(() => {
        if (!unitId || !organizationUuid) {
            setState(EMPTY);
            return;
        }

        let cancelled = false;
        setState({
            ...EMPTY,
            isLoading: true,
            leaseForbidden: !canViewLease,
            tenantForbidden: !canViewTenant,
            rentsForbidden: !canViewRent,
        });

        void (async () => {
            try {
                if (!canViewLease) {
                    if (!cancelled) setState((previous) => ({ ...previous, isLoading: false }));
                    return;
                }

                const leasePage = await fetchLeasesByUnit(organizationUuid, unitId);
                if (cancelled) return;
                const activeLease = pickActiveLease(leasePage.items);

                let tenant: TenantItem | null = null;
                let tenantForbidden = !canViewTenant;
                if (activeLease && canViewTenant) {
                    try {
                        tenant = await fetchTenantByUuid(activeLease.tenantId);
                        tenantForbidden = false;
                    } catch (cause) {
                        tenantForbidden = isForbidden(cause);
                    }
                }

                if (cancelled) return;

                let rents: RentItem[] = [];
                let rentsTruncated = false;
                let rentsForbidden = !canViewRent;
                if (activeLease && canViewRent) {
                    try {
                        const rentPage = await fetchRentsByLease(organizationUuid, activeLease.id);
                        rents = rentPage.items;
                        // La liste est bornée par la pagination : une page pleine
                        // signale une troncature probable (le `total` backend est
                        // compté avant le filtre d'accès, il ne sert pas ici).
                        rentsTruncated = rentPage.items.length >= REFERENCE_LIMIT;
                        rentsForbidden = false;
                    } catch (cause) {
                        rentsForbidden = isForbidden(cause);
                    }
                }

                if (cancelled) return;
                setState({
                    isLoading: false,
                    error: null,
                    leaseForbidden: false,
                    tenantForbidden,
                    rentsForbidden,
                    activeLease,
                    tenant,
                    rents,
                    rentsTruncated,
                });
            } catch (cause) {
                if (cancelled) return;
                setState({
                    ...EMPTY,
                    leaseForbidden: !canViewLease,
                    tenantForbidden: !canViewTenant,
                    rentsForbidden: !canViewRent,
                    error: cause instanceof ApiError ? cause.message : 'Impossible de charger les informations de location de cette unité.',
                });
            }
        })();

        return () => { cancelled = true; };
    }, [unitId, organizationUuid, canViewLease, canViewTenant, canViewRent]);

    return state;
}
