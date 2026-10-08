import { apiClient } from '../../../../../services/api/client';
import type { Feedback } from '../../../../../services/api/api.types';
import type {
    UserApiResponse,
    UserListData,
    UserRow,
    UserSuspendPayload,
    UserSuspendResult,
} from '../types/user.types';

/**
 * usersService
 *
 * Contrat HTTP des endpoints utilisateurs (`src/Controller/Api/Identity/UserController.php`).
 * Toute réponse transite par l'enveloppe `Feedback` : `data` porte soit un
 * `UserResponse` (suspend), soit un objet paginé `{ items, total, page, limit }` (list).
 *
 * Les routes API commencent par `/api/v1/identity/users` ; le `apiClient`
 * a `baseURL = '/api'`, donc `url` commence à `/v1/...`.
 */
export const usersService = {
    /** `GET /api/v1/identity/users` — liste paginée (SUPER_ADMIN : tous les comptes). */
    async list(params?: { page?: number; limit?: number; search?: string }): Promise<UserRow[]> {
        const query: Record<string, string | number> = {
            page: params?.page ?? 1,
            limit: params?.limit ?? 100,
            ...(params?.search ? { search: params.search } : {}),
        };
        const { data } = await apiClient.get<Feedback<UserListData>>('/v1/identity/users', { params: query });
        return data.data.items.map(toRow);
    },

    /**
     * `POST /api/v1/identity/users/{uuid}/suspend` — désactive le compte et
     * notifie l'utilisateur par email. Le backend archive le motif dans
     * l'audit ; un échec du mailer remonte en `warnings` (non bloquant).
     */
    async suspend(uuid: string, payload: UserSuspendPayload = {}): Promise<UserSuspendResult> {
        const { data } = await apiClient.post<Feedback<UserApiResponse>>(`/v1/identity/users/${uuid}/suspend`, payload);
        return {
            user: toRow(data.data),
            flushDescription: data.flushDescription,
            warnings: data.warnings,
        };
    },
};

/**
 * Projette le DTO backend `UserResponse` vers la ligne du tableau.
 * Séparation réponse API / affichage : la page n'a jamais à connaître le DTO.
 *
 * `organization` et `role` sont dérivés ici pour que la table, les filtres et
 * le tri lisent un champ simple : rôle de plateforme sinon premier
 * rattachement métier, « Plateforme » pour un compte sans affiliation.
 */
function toRow(response: UserApiResponse): UserRow {
    const firstMembership = response.memberships?.[0];

    return {
        id: response.id,
        name: response.fullName,
        email: response.email,
        phone: response.phone,
        profilePhoto: response.profilePhoto,
        platformRole: response.platformRole,
        memberships: response.memberships ?? [],
        organization: firstMembership ? firstMembership.organizationName : (response.platformRole ? 'Plateforme' : '—'),
        role: response.platformRole ?? firstMembership?.role ?? null,
        status: response.isActive ? 'active' : 'inactive',
        lastLoginAt: response.lastLoginAt,
    };
}

/**
 * Résout une URL d'image utilisable dans `<img src>` à partir de la valeur
 * `profilePhoto` du DTO : l'API peut renvoyer soit une URL publique, soit un
 * chemin relatif (`profiles/…`), soit un chemin absolu (`/uploads/profiles/…`).
 */
export function photoHref(photo: string | null): string | null {
    if (!photo) return null;
    if (/^(https?:)?\/\//.test(photo) || photo.startsWith('/') || photo.startsWith('data:')) return photo;
    return `/uploads/${photo}`;
}
