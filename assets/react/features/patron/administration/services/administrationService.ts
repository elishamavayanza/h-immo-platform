import { apiClient } from '../../../../../services/api/client';
import type { Feedback } from '../../../../../services/api/api.types';
import type {
    AdminApiUser,
    AdminListData,
    AdministrationMutationResult,
    CreateAdminPayload,
    SuspendMemberPayload,
    UpdateMemberPayload,
} from '../types/administration.types';

/**
 * administrationService
 *
 * Contrat HTTP de la page Administration du PATRON
 * (`src/Controller/Api/Identity/UserController.php` et
 * `src/Controller/Api/Identity/OrganizationUserController.php`).
 * Toute réponse transite par l'enveloppe `Feedback` ; le `apiClient` a
 * `baseURL = '/api'`, donc les `url` commencent à `/v1/...`.
 *
 * La création passe par `/organization-users/create-admin` (le PATRON ne
 * délègue que `ADMIN_IMMOBILIER`), les listes et mutations de fiche par
 * `/identity/users`. La liste est bornée côté backend aux organizations de
 * l'appelant ; le service ne la retient ensuite que pour l'organization
 * active, pour qu'un PATRON multi-tenant n'affiche pas l'équipe d'une autre.
 */
export const administrationService = {
    /**
     * `GET /v1/identity/users` — membres des organizations de l'appelant,
     * filtrés sur l'organization active (UUID en path, jamais depuis le body).
     */
    async list(organizationUuid: string, params?: { page?: number; limit?: number; search?: string }): Promise<AdminApiUser[]> {
        const query: Record<string, string | number> = {
            page: params?.page ?? 1,
            limit: params?.limit ?? 100,
            ...(params?.search ? { search: params.search } : {}),
        };
        const { data } = await apiClient.get<Feedback<AdminListData>>('/v1/identity/users', { params: query });

        return data.data.items.filter((user) =>
            user.memberships?.some((membership) => membership.organizationId === organizationUuid)
        );
    },

    /** `POST /v1/identity/organization-users/create-admin` — création d'un ADMIN_IMMOBILIER. */
    async createAdmin(payload: CreateAdminPayload): Promise<{ flushDescription: string | null; warnings: Record<string, string> }> {
        const { data } = await apiClient.post<Feedback<unknown>>('/v1/identity/organization-users/create-admin', payload);

        return { flushDescription: data.flushDescription, warnings: data.warnings };
    },

    /** `PUT /v1/identity/users/{uuid}` — édition de la fiche (nom, téléphone). */
    async updateMember(uuid: string, payload: UpdateMemberPayload): Promise<AdministrationMutationResult> {
        const { data } = await apiClient.put<Feedback<AdminApiUser>>(`/v1/identity/users/${uuid}`, payload);

        return { user: data.data, flushDescription: data.flushDescription, warnings: data.warnings };
    },

    /** `POST /v1/identity/users/{uuid}/suspend` — désactivation + notification email. */
    async suspendMember(uuid: string, payload: SuspendMemberPayload = {}): Promise<AdministrationMutationResult> {
        const { data } = await apiClient.post<Feedback<AdminApiUser>>(`/v1/identity/users/${uuid}/suspend`, payload);

        return { user: data.data, flushDescription: data.flushDescription, warnings: data.warnings };
    },
};