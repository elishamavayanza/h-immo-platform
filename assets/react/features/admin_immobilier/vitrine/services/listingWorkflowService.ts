import { apiClient } from '../../../../../services/api/client';
import type { Feedback } from '../../../../../services/api/api.types';
import type { ListingCandidate, ListingDraftPayload, ListingWorkflowItem } from '../types/listingWorkflow.types';

interface ListingPage { items: ListingWorkflowItem[]; total: number; page: number; pages: number; }
interface CandidatePage { items: ListingCandidate[]; total: number; page: number; pages: number; }

export async function fetchListingCandidates(organizationUuid: string, page = 1, cityUuid?: string): Promise<CandidatePage> {
    const params = new URLSearchParams({ page: String(page), limit: '100' });
    if (cityUuid) params.set('cityUuid', cityUuid);
    const { data } = await apiClient.get<Feedback<CandidatePage>>(`/v1/organizations/${organizationUuid}/listing-candidates?${params.toString()}`);
    return data.data;
}

export async function fetchListings(organizationUuid: string, cityUuid?: string): Promise<ListingPage> {
    const params = new URLSearchParams({ limit: '100' });
    if (cityUuid) params.set('cityUuid', cityUuid);
    const { data } = await apiClient.get<Feedback<ListingPage>>(`/v1/organizations/${organizationUuid}/listings?${params.toString()}`);
    return data.data;
}

export async function fetchListing(uuid: string): Promise<ListingWorkflowItem> {
    const { data } = await apiClient.get<Feedback<ListingWorkflowItem>>(`/v1/listings/${uuid}`);
    return data.data;
}

export async function createListingDraft(unitUuid: string, payload: ListingDraftPayload): Promise<ListingWorkflowItem> {
    const { data } = await apiClient.post<Feedback<ListingWorkflowItem>>(`/v1/units/${unitUuid}/listing`, payload);
    return data.data;
}

export async function updateListingDraft(uuid: string, payload: ListingDraftPayload): Promise<ListingWorkflowItem> {
    const { data } = await apiClient.put<Feedback<ListingWorkflowItem>>(`/v1/listings/${uuid}`, payload);
    return data.data;
}

export async function listingAction(uuid: string, action: 'submit' | 'request-corrections' | 'queue-publication' | 'publish' | 'reject' | 'withdraw', reason?: string): Promise<void> {
    await apiClient.post(`/v1/listings/${uuid}/${action}`, reason === undefined ? {} : { reason });
}
