import type { OrganizationRole, PlatformRole } from '../../../../../services/api/api.types';

export type UserStatus = 'active' | 'inactive';

/**
 * Rattachement renvoyé par `UserResponse.memberships`
 * (`src/Dto/Response/Identity/UserResponse.php`).
 */
export interface UserMembership {
    organizationId: string;
    organizationName: string;
    role: OrganizationRole;
}

/**
 * Ligne d'un compte telle qu'affichée dans le tableau de gestion.
 * Mappée depuis `UserApiResponse` (DTO `UserResponse`).
 *
 * `organization` et `role` sont des champs *d'affichage* dérivés des
 * rattachements (premier rattachement, sinon « Plateforme »/rôle de
 * plateforme) : la table trie et les filtres lisent ces champs, tandis que
 * `memberships` (donnée API complète) alimente le filtre par organisation.
 */
export interface UserRow {
    id: string;
    name: string;
    email: string;
    phone: string | null;
    profilePhoto: string | null;
    platformRole: PlatformRole | null;
    memberships: UserMembership[];
    organization: string;
    role: OrganizationRole | 'super_admin' | null;
    status: UserStatus;
    lastLoginAt: string | null;
}

/**
 * `UserResponse` — DTO sérialisé par le backend
 * (`src/Dto/Response/Identity/UserResponse.php`).
 * `id` est l'UUID public (jamais l'id technique interne).
 */
export interface UserApiResponse {
    id: string;
    email: string;
    fullName: string;
    phone: string | null;
    profilePhoto: string | null;
    platformRole: PlatformRole | null;
    isActive: boolean;
    memberships: UserMembership[];
    lastLoginAt: string | null;
    createdAt: string;
    updatedAt: string;
}

/**
 * Corps paginé de `GET /v1/identity/users`, présent dans
 * `Feedback.data` (`{ items, total, page, limit }`).
 */
export interface UserListData {
    items: UserApiResponse[];
    total: number;
    page: number;
    limit: number;
}

/** `UserSuspendRequest` : motif facultatif, joint à l'email et à l'audit. */
export interface UserSuspendPayload {
    reason?: string | null;
}

/** Résultat d'une suspension : compte mis à jour + feedback backend affichable. */
export interface UserSuspendResult {
    user: UserRow;
    flushDescription: string | null;
    warnings: Record<string, string>;
}

export type UserRoleFilter = 'all' | OrganizationRole | 'super_admin';
export type UserOrganizationFilter = 'all' | 'platform' | string;
