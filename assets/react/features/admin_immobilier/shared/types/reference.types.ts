/**
 * reference.types.ts — Miroirs TypeScript des DTO de référence partagés par
 * les pages ADMIN_IMMOBILIER (patrimoine, location, dépenses, vitrine,
 * personnel).
 *
 * Chaque interface reflète un `*Response` du backend (`src/Dto/Response/`).
 * Les champs sont ceux réellement sérialisés : les colonnes de maquette sans
 * équivalent API (statut d'un bien, solde d'un locataire…) n'existent pas ici
 * et ne doivent pas être réinventés côté client.
 */

/** Corps paginé porté par `Feedback.data` des listes (`{items, total, …}`). */
export interface ListPage<T> {
    items: T[];
    total: number;
    page?: number;
    limit?: number;
}

/** `CityResponse` — `GET /api/v1/property/cities`. */
export interface CityItem {
    id: string;
    organizationId: string;
    name: string;
    code: string;
    province: string | null;
    country: string | null;
    status: string;
}

/** `ParcelResponse` — `GET /api/v1/parcels`. */
export interface ParcelItem {
    id: string;
    cityId: string;
    reference: string;
    titleNumber: string | null;
    name: string;
    address: string;
    quarter: string | null;
    area: string;
    latitude: string | null;
    longitude: string | null;
    description: string | null;
}

/** `BuildingResponse` — `GET /api/v1/property/buildings`. */
export interface BuildingItem {
    id: string;
    parcelId: string;
    reference: string;
    name: string;
    type: string;
    numberOfFloors: number | null;
    description: string | null;
}

/** `UnitResponse` — `GET /api/v1/units` (`isPublished` = annonce vitrine). */
export interface UnitItem {
    id: string;
    buildingId: string;
    reference: string;
    type: string;
    floor: number;
    surface: string;
    bedrooms: number | null;
    rooms: number | null;
    bathrooms: number | null;
    monthlyRent: string;
    currency: string;
    description: string | null;
    isPublished: boolean;
}

/** `TenantResponse` — `GET /api/v1/tenants`. */
export interface TenantItem {
    id: string;
    organizationId: string;
    type: string;
    fullName: string | null;
    companyName: string | null;
    phone: string;
    email: string | null;
    address: string | null;
    notes: string | null;
}

/** `RentResponse` — `GET /api/v1/rents` (`status` = statut calculé, `isOverdue` dérivé). */
export interface RentItem {
    id: string;
    leaseId: string;
    period: string;
    dueDate: string;
    amount: string;
    currency: string;
    exchangeRate: string | null;
    originalAmount: string | null;
    originalCurrency: string | null;
    status: string;
    isOverdue: boolean;
    createdAt: string;
    updatedAt: string;
}

/** `LeaseResponse` — `GET /api/v1/leases`. */
export interface LeaseItem {
    id: string;
    organizationId: string;
    tenantId: string;
    unitId: string;
    reference: string;
    startDate: string;
    endDate: string | null;
    depositAmount: string | null;
    monthlyRent: string;
    exchangeRate: string | null;
    referenceCurrency: string | null;
    currency: string;
    status: string;
    terminationDate: string | null;
    terminationReason: string | null;
    notes: string | null;
    terms: string | null;
    createdAt: string;
    updatedAt: string;
}

/** Catalogue patrimonial des 4 niveaux, chargé en parallèle par les pages. */
export interface PropertyCatalog {
    cities: ListPage<CityItem>;
    parcels: ListPage<ParcelItem>;
    buildings: ListPage<BuildingItem>;
    units: ListPage<UnitItem>;
}

/**
 * `ExpenseResponse` — `GET /api/v1/expenses`.
 *
 * Pas de champ `status` : une dépense est une opération enregistrée, son
 * annulation passe par une contre-écriture côté backend, pas par un statut
 * mutable exposé en lecture de liste.
 */
export interface ExpenseItem {
    id: string;
    organizationId: string;
    cityId: string;
    parcelId: string | null;
    buildingId: string | null;
    unitId: string | null;
    workerId: string | null;
    category: string;
    amount: string;
    currency: string;
    expenseDate: string;
    method: string | null;
    supplier: string | null;
    reference: string | null;
    notes: string | null;
    createdAt: string;
}

/** `WorkerResponse` — `GET /api/v1/workers`. */
export interface WorkerItem {
    id: string;
    organizationId: string;
    fullName: string;
    phone: string;
    email: string | null;
    nationalId: string | null;
    address: string | null;
    notes: string | null;
    createdAt: string;
}

/** `WorkerAssignmentResponse` — `GET /api/v1/worker-assignments`. */
export interface WorkerAssignmentItem {
    id: string;
    workerId: string;
    cityId: string;
    parcelId: string | null;
    buildingId: string | null;
    unitId: string | null;
    role: string;
    monthlySalary: string;
    currency: string;
    startDate: string;
    endDate: string | null;
    notes: string | null;
    createdAt: string;
}
