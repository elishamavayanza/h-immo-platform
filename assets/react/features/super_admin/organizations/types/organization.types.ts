export type OrganizationStatus = 'active' | 'suspended' | 'inactive';

/**
 * Ligne d'une organisation telle qu'affichée dans le tableau de gestion.
 * Mappée depuis `OrganizationApiResponse` (DTO `OrganizationResponse`).
 */
export interface OrganizationRow {
    id: string;
    name: string;
    code: string;
    email: string;
    phone: string;
    address: string;
    city: string;
    logo: string | null;
    status: OrganizationStatus;
    createdAt: string;
    suspensionEndsAt?: string | null;
    showcaseEnabled: boolean;
    maxCities: number | null;
    maxParcels: number | null;
    maxBuildings: number | null;
    maxUnits: number | null;
    maxMembers: number | null;
}

export type OrganizationStatusFilter = OrganizationStatus | 'all';

/**
 * `OrganizationResponse` — DTO sérialisé par le backend
 * (`src/Dto/Response/Identity/OrganizationResponse.php`).
 * `id` est l'UUID public (jamais l'id technique interne).
 */
export interface OrganizationApiResponse {
    id: string;
    name: string;
    code: string;
    logo: string | null;
    email: string;
    phone: string;
    address: string | null;
    city: string | null;
    country: string | null;
    status: OrganizationStatus;
    createdAt: string;
    updatedAt: string;
    suspensionEndsAt?: string | null;
    showcaseEnabled: boolean;
    maxCities: number | null;
    maxParcels: number | null;
    maxBuildings: number | null;
    maxUnits: number | null;
    maxMembers: number | null;
}

/**
 * Corps paginé de `GET /v1/identity/organizations`, présent dans
 * `Feedback.data` (`{ items, total, page, limit }`).
 */
export interface OrganizationListData {
    items: OrganizationApiResponse[];
    total: number;
    page: number;
    limit: number;
}

export interface OrganizationCapacityData {
    limits: Record<'cities' | 'parcels' | 'buildings' | 'units' | 'members', number | null>;
    usage: Record<'cities' | 'parcels' | 'buildings' | 'units' | 'members', number>;
    showcaseEnabled: boolean;
}

/**
 * `OrganizationRequest` côté création : les champs organisation ET
 * PATRON sont obligatoires (groupe de validation `create`). Le backend crée
 * le tenant et son PATRON dans une unique transaction : redonner ces champs
 * est le contrat, pas une redondance.
 */
export interface OrganizationCreatePayload {
    name: string;
    code: string;
    email: string;
    phone: string;
    city?: string;
    address?: string;
    country?: string;
    status?: OrganizationStatus;
    patronFullName: string;
    patronEmail: string;
    patronPhone: string;
    maxCities: number | null;
    maxParcels: number | null;
    maxBuildings: number | null;
    maxUnits: number | null;
    maxMembers: number | null;
    capacitySettingsProvided: boolean;
    /** Fichier logos retaillé validé par l'ImageEditor : uploadé APRÈS la création atomique (le tenant doit exister pour le recevoir). */
    logoFile?: File | null;
}

/**
 * `OrganizationRequest` côté mise à jour : les champs PATRON ne sont plus
 * exigés (groupe de validation `update`). Les plafonds sont envoyés ensemble
 * avec `capacitySettingsProvided` afin que `null` reste une valeur explicite
 * (« sans limite ») sans effacer un réglage lors d’une mise à jour partielle.
 */
export type OrganizationUpdatePayload = Partial<{
    name: string;
    code: string;
    email: string;
    phone: string;
    city: string;
    address: string;
    country: string;
    status: OrganizationStatus;
    showcaseEnabled: boolean;
    maxCities: number | null;
    maxParcels: number | null;
    maxBuildings: number | null;
    maxUnits: number | null;
    maxMembers: number | null;
    capacitySettingsProvided: boolean;
    /** Nouveau logo retaillé par l'ImageEditor : envoyé via l'endpoint média du tenant après le PUT. */
    logoFile?: File | null;
    /** Retirer le logo actuel du tenant (DELETE sur l'endpoint média). */
    removeLogo?: boolean;
}>;

/** Résultat d'une création : l'organisation créée + feedback backend affichable. */
export interface OrganizationCreateResult {
    organization: OrganizationRow;
    flushDescription: string | null;
    warnings: Record<string, string>;
}

/** Résultat d'une transition de statut (suspension / réactivation) : organisation + feedback backend. */
export interface OrganizationTransitionResult {
    organization: OrganizationRow;
    flushDescription: string | null;
    warnings: Record<string, string>;
}
