import { useCallback, useEffect, useRef, useState } from 'react';
import { useToast } from '../../../../app/layout/MainLayout/contexts/ToastContext';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { ApiError } from '../../../../../services/api/api.types';
import type { OrganizationRole } from '../../../../../services/api/api.types';
import { fetchListings, listingAction } from '../services/listingWorkflowService';
import type { ListingDraftPayload, ListingWorkflowItem } from '../types/listingWorkflow.types';
import { createListingDraft, updateListingDraft } from '../services/listingWorkflowService';

export function useListingWorkflow(role: OrganizationRole | null, cityUuid: string | null) {
    const { currentOrganization } = useOrganization();
    const organizationUuid = currentOrganization?.uuid ?? null;
    const { push } = useToast();
    const [items, setItems] = useState<ListingWorkflowItem[]>([]);
    const [isLoading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [pendingUuid, setPendingUuid] = useState<string | null>(null);
    const requestId = useRef(0);

    const reload = useCallback(async () => {
        const currentRequestId = ++requestId.current;
        if (!organizationUuid) { setItems([]); setError(null); setLoading(false); return; }
        if (role === 'admin_ville' && !cityUuid) { setItems([]); setError(null); setLoading(false); return; }
        setLoading(true); setError(null);
        try {
            const page = await fetchListings(organizationUuid, role === 'admin_ville' ? cityUuid ?? undefined : undefined);
            if (currentRequestId === requestId.current) setItems(page.items);
        } catch (cause) {
            if (currentRequestId === requestId.current) setError(cause instanceof ApiError ? cause.message : 'Impossible de charger les annonces.');
        } finally {
            if (currentRequestId === requestId.current) setLoading(false);
        }
    }, [organizationUuid, role, cityUuid]);
    useEffect(() => { void reload(); }, [reload]);

    const run = useCallback(async (uuid: string, label: string, operation: () => Promise<unknown>) => {
        setPendingUuid(uuid);
        try { await operation(); push('success', label); await reload(); }
        catch (cause) { push('error', cause instanceof ApiError ? cause.message : 'Une erreur inattendue est survenue.'); throw cause; }
        finally { setPendingUuid(null); }
    }, [push, reload]);

    const create = (unitUuid: string, payload: ListingDraftPayload) => run(unitUuid, 'Le brouillon de l’annonce a été créé.', () => createListingDraft(unitUuid, payload));
    const update = (item: ListingWorkflowItem, payload: ListingDraftPayload) => run(item.uuid, 'Le brouillon a été enregistré.', () => updateListingDraft(item.uuid, payload));
    const act = (item: ListingWorkflowItem, action: 'submit' | 'request-corrections' | 'queue-publication' | 'publish' | 'reject' | 'withdraw', success: string, reason?: string) => run(item.uuid, success, () => listingAction(item.uuid, action, reason));

    return { items, isLoading, error, pendingUuid, reload, create, update, act };
}
