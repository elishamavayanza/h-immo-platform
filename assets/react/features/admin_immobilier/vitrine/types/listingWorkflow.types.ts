export type ListingStatus = 'draft' | 'pending_review' | 'corrections_requested' | 'pending_publication' | 'published' | 'rejected' | 'withdrawn';
export type ListingOfferType = 'rent' | 'sale';

export interface ListingHistoryItem {
    action: string;
    oldStatus: string | null;
    newStatus: string;
    reason: string | null;
    createdAt: string;
    uuid: string;
    actor: string;
}

export interface ListingWorkflowItem {
    uuid: string;
    unitUuid: string;
    organizationUuid: string;
    cityUuid: string;
    city: string;
    unitReference: string;
    buildingName?: string;
    parcelName?: string;
    unitType?: string;
    floor?: number;
    floorLabel?: string | null;
    surface?: string;
    bedrooms?: number | null;
    rooms?: number | null;
    bathrooms?: number | null;
    photoCount?: number;
    coverPhotoUrl?: string | null;
    createdByUuid: string;
    status: ListingStatus;
    title: string;
    description: string;
    offerType: ListingOfferType;
    price: string;
    currency: 'USD' | 'CDF';
    publishedAt: string | null;
    updatedAt: string;
    history?: ListingHistoryItem[];
}

export interface ListingDraftPayload {
    title: string;
    description: string;
    offerType: ListingOfferType;
    price: string;
    currency: 'USD' | 'CDF';
}

export interface ListingCandidate {
    uuid: string;
    reference: string;
    type: string;
    cityUuid: string;
    city: string;
    building: string;
    parcel: string;
    floor: number;
    floorLabel: string | null;
    surface: string;
    bedrooms: number | null;
    rooms: number | null;
    bathrooms: number | null;
    monthlyRent: string;
    currency: 'USD' | 'CDF';
    photoCount: number;
    coverPhotoUrl: string | null;
}
