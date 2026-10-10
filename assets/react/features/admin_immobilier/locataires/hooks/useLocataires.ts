import { useCallback, useEffect, useMemo, useState } from 'react';

import { useToast } from '../../../../app/layout/MainLayout/contexts/ToastContext';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { ApiError } from '../../../../../services/api/api.types';
import {
    archiveTenant,
    activateLease,
    createTenant,
    fetchLocataires,
    updateTenant,
    type CreateTenantPayload,
    type UpdateTenantPayload,
} from '../services/locatairesService';
import { createLease, type CreateLeasePayload } from '../services/locatairesService';
import type { LocataireRow, LocatairesData } from '../types/locataire.types';

function actionErrorMessage(cause: unknown): string {
    return cause instanceof ApiError ? cause.message : 'Une erreur inattendue est survenue. Réessayez.';
}

function isValidationError(cause: unknown): boolean {
    return cause instanceof ApiError && cause.status === 422;
}

const runAction = async (push: ReturnType<typeof useToast>['push'], successMessage: string, action: () => Promise<unknown>): Promise<void> => {
    try {
        await action();
        push('success', successMessage);
    } catch (cause) {
        if (!isValidationError(cause)) push('error', actionErrorMessage(cause));
        throw cause;
    }
};

/**
 * Chargement des locataires de l'organization active + filtrage local
 * (recherche, type) sur le jeu chargé, et archivage/création/modification d'un locataire.
 */
export function useLocataires() {
    const { currentOrganization } = useOrganization();
    const organizationUuid = currentOrganization?.uuid ?? null;
    const { push } = useToast();

    const [data, setData] = useState<LocatairesData | null>(null);
    const [isLoading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const [search, setSearch] = useState('');
    const [type, setType] = useState('all');

    const [pending, setPending] = useState<LocataireRow | null>(null);
    const [isArchiving, setArchiving] = useState(false);

    const reload = useCallback(async () => {
        if (!organizationUuid) {
            setData(null);
            setLoading(false);

            return;
        }
        setLoading(true);
        setError(null);
        try {
            setData(await fetchLocataires(organizationUuid));
        } catch (cause) {
            setError(cause instanceof ApiError ? cause.message : 'Impossible de charger les locataires de l’organisation.');
        } finally {
            setLoading(false);
        }
    }, [organizationUuid]);

    useEffect(() => { void reload(); }, [reload]);

    const archive = useCallback(async () => {
        if (!pending) return;
        setArchiving(true);
        try {
            const message = await archiveTenant(pending.id);
            push('success', message);
            await reload();
        } catch (cause) {
            push('error', actionErrorMessage(cause));
        } finally {
            setArchiving(false);
            setPending(null);
        }
    }, [pending, reload, push]);

    const createTenantAction = useCallback(async (payload: CreateTenantPayload): Promise<void> => {
        await runAction(push, 'Locataire créé.', () => createTenant(payload));
        await reload();
    }, [push, reload]);

    const updateTenantAction = useCallback(async (uuid: string, payload: UpdateTenantPayload): Promise<void> => {
        await runAction(push, 'Locataire mis à jour.', () => updateTenant(uuid, payload));
        await reload();
    }, [push, reload]);

    const createLeaseAction = useCallback(async (payload: CreateLeasePayload): Promise<void> => {
        await runAction(push, 'Bail créé.', () => createLease(payload));
        await reload();
    }, [push, reload]);

    const activateLeaseAction = useCallback(async (leaseUuid: string): Promise<void> => {
        await runAction(push, 'Bail activé.', () => activateLease(leaseUuid));
        await reload();
    }, [push, reload]);

    const rows = useMemo(() => {
        const query = search.trim().toLocaleLowerCase('fr');
        return (data?.rows ?? []).filter((row) =>
            (!query || [row.name, row.sublabel, row.unitReference ?? ''].some((value) => value.toLocaleLowerCase('fr').includes(query)))
            && (type === 'all' || row.type === type),
        );
    }, [data, search, type]);

    return {
        data,
        rows,
        isLoading,
        error,
        reload,
        search,
        setSearch,
        type,
        setType,
        pending,
        requestArchive: setPending,
        cancelArchive: () => setPending(null),
        confirmArchive: archive,
        isArchiving,
        createTenant: createTenantAction,
        updateTenant: updateTenantAction,
        createLease: createLeaseAction,
        activateLease: activateLeaseAction,
    };
}
