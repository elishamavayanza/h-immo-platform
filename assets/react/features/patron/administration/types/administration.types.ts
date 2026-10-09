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
    isSelf: boolean;
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
 * (`CreateAdminRequest`). Le PATRON ne délègue que `admin_immobilier` :
 * c'est la seule valeur émise côté UI, le rôle patron n'est pas délégable.
 */
export interface CreateAdminPayload {
    organizationUuid: string;
    role: 'admin_immobilier';
    email: string;
    fullName: string;
    phone: string;
}

/**
 * Corps de `PUT /v1/identity/users/{uuid}` (groupe de validation `update`).
 * Seuls les champs édités sont envoyés ; `isActive`/`platformRole` restent
 * hors périmètre (ils portent une autorisation propre côté backend).
 */
export interface UpdateMemberPayload {
    firstName: string;
    lastName: string;
    phone: string;
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