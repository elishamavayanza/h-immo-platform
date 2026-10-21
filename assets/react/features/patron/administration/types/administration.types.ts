import type { OrganizationRole } from '../../../../../services/api/api.types';

export type TeamMemberStatus = 'active' | 'inactive';

/**
 * Ligne d'affichage d'un membre de l'équipe (espace PATRON).
 *
 * Mappée depuis `UserResponse` (`GET /v1/identity/users`) filtré sur
 * l'organization active. `role` est le rôle de rattachement POUR cette
 * organization ; `isSelf` permet de neutraliser l'action Suspendre sur le
 * compte de l'appelant (auto-suspension refusée par le backend en 422).
 */
export interface TeamMember {
    id: string;
    name: string;
    email: string;
    phone: string | null;
    role: OrganizationRole;
    status: TeamMemberStatus;
    cityNames?: string[];
}

/** Rattachement organisationnel renvoyé par `UserResponse.memberships`. */
export interface AdminUserMembership {
    organizationId: string;
    organizationName: string;
    role: OrganizationRole;
}

/**
 * `UserResponse` — DTO sérialisé par le backend sur `GET /v1/identity/users`
 * et les mutations `PUT /{uuid}` / `POST /{uuid}/suspend`
 * (`src/Dto/Response/Identity/UserResponse.php`). `id` est l'UUID public.
 */
export interface AdminApiUser {
    id: string;
    email: string;
    fullName: string;
    phone: string | null;
    profilePhoto: string | null;
    platformRole: string | null;
    isActive: boolean;
    memberships: AdminUserMembership[];
    lastLoginAt: string | null;
    createdAt: string;
    updatedAt: string;
}

/** Corps paginé de `GET /v1/identity/users`, présent dans `Feedback.data`. */
export interface AdminListData {
    items: AdminApiUser[];
    total: number;
    page: number;
    limit: number;
}

/**
 * Corps de `POST /v1/identity/organization-users/create-admin`
 * (`CreateAdminRequest`). Le PATRON délègue `admin_immobilier` ou
 * `admin_ville` ; le rôle `patron` n'est pas délégable. Pour un ADMIN_VILLE,
 * `cityUuids` doit contenir au moins une ville de l'organisation
 * (`Assert\Count(min: 1)`), ignoré pour un ADMIN_IMMOBILIER.
 */
export interface CreateAdminPayload {
    organizationUuid: string;
    role: 'admin_immobilier' | 'admin_ville';
    email: string;
    fullName: string;
    phone: string;
    cityUuids?: string[];
}

/**
 * Corps de `PUT /v1/identity/users/{uuid}` (groupe de validation `update`).
 * Seuls les champs édités sont envoyés ; `platformRole` reste hors périmètre
 * (il porte une autorisation propre côté backend). `isActive` n'est émis que
 * par la réactivation explicite d'un compte suspendu.
 */
export interface UpdateMemberPayload {
    firstName?: string;
    lastName?: string;
    phone?: string;
    isActive?: boolean;
}

/** Corps optionnel de `POST /v1/identity/users/{uuid}/suspend`. */
export interface SuspendMemberPayload {
    reason?: string;
}

/** Résultat d'une mutation : compte mis à jour + feedback backend affichable. */
export interface AdministrationMutationResult {
    user: AdminApiUser;
    flushDescription: string | null;
    warnings: Record<string, string>;
}

/**
 * Champ `data` de `GET /v1/identity/user-cities/user/{uuid}`
 * (`UserCityResponse` : rattachement User ↔ City d'un ADMIN_VILLE).
 */
export interface UserCityLink {
    id: string;
    userId: string;
    cityId: string;
    createdAt: string;
    canManagePersonnel: boolean;
}

/** Ville sélectionnable pour l'affectation d'un ADMIN_VILLE (`CityItem`). */
export interface AdminCityOption {
    uuid: string;
    name: string;
}

/**
 * Coordonnées de l'organisation (espace PATRON), sous-ensemble éditable de
 * `OrganizationResponse` (`GET|PUT /v1/identity/organizations/{uuid}`).
 * Seuls `name`, `email` et `phone` sont obligatoires ; le statut et la
 * vitrine ne sont pas modifiés par cette carte d'identité.
 */
export interface OrganizationProfile {
    uuid: string;
    name: string;
    code: string;
    email: string;
    phone: string;
    address: string | null;
    city: string | null;
    country: string | null;
    logo: string | null;
}

/** Corps de `PUT /v1/identity/organizations/{uuid}` (groupe `update`). */
export interface UpdateOrganizationPayload {
    name?: string;
    email?: string;
    phone?: string;
    address?: string;
    city?: string;
    country?: string;
}
