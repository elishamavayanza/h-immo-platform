import { apiClient } from '../../../../../services/api/client';
import type { Feedback } from '../../../../../services/api/api.types';
import type {
    AdminApiUser,
    AdminListData,
    AdministrationMutationResult,
    CreateAdminPayload,
    OrganizationProfile,
    SuspendMemberPayload,
    UpdateMemberPayload,
    UpdateOrganizationPayload,
    UserCityLink,
} from '../types/administration.types';

/** Corps paginé du listing `GET /v1/identity/user-cities/user/{uuid}`. */
interface UserCityListData {
    items: UserCityLink[];
    total: number;
    page: number;
    limit: number;
}

/** Forme d'`OrganizationResponse` utilisée par la carte Organisation. */
interface OrganizationApiResponse {
    id: string;
    name: string;
    code: string;
    logo: string | null;
    email: string;
    phone: string;
    address: string | null;
    city: string | null;
    country: string | null;
}

function toProfile(response: OrganizationApiResponse): OrganizationProfile {
    return {
        uuid: response.id,
        name: response.name,
        code: response.code,
        logo: response.logo,
        email: response.email,
        phone: response.phone,
        address: response.address,
        city: response.city,
        country: response.country,
    };
}

/**
 * administrationService
 *
 * Contrat HTTP de la page Administration du PATRON
 * (`src/Controller/Api/Identity/UserController.php` et
 * `src/Controller/Api/Identity/OrganizationUserController.php`).
 * Toute réponse transite par l'enveloppe `Feedback` ; le `apiClient` a
 * `baseURL = '/api'`, donc les `url` commencent à `/v1/...`.
 *
 * La création passe par `/organization-users/create-admin` (le PATRON
 * délègue `ADMIN_IMMOBILIER` ou `ADMIN_VILLE`, ce dernier avec ses villes),
 * les listes et mutations de fiche par `/identity/users`, les périmètres
 * géographiques par `/identity/user-cities` et les coordonnées de
 * l'organisation par `/identity/organizations`. La liste est bornée côté
 * backend aux organizations de l'appelant ; le service ne la retient ensuite
 * que pour l'organization active, pour qu'un PATRON multi-tenant n'affiche
 * pas l'équipe d'une autre.
 */
export const administrationService = {
    async listRoleAssignments(organizationUuid: string): Promise<{
        items: Array<{ assignmentUuid: string; userUuid: string; name: string; email: string; active: boolean; role: string; cities: Array<{ uuid: string; name: string }> }>;
        candidates: Array<{ uuid: string; name: string; email: string; roles: string[] }>;
    }> {
        const { data } = await apiClient.get<Feedback<{
            items: Array<{ assignmentUuid: string; userUuid: string; name: string; email: string; active: boolean; role: string; cities: Array<{ uuid: string; name: string }> }>;
            candidates: Array<{ uuid: string; name: string; email: string; roles: string[] }>;
        }>>(`/v1/identity/organization-users/role-assignments/organization/${organizationUuid}`);
        return data.data;
    },

    async assignExistingRole(payload: { organizationUuid: string; userUuid: string; role: 'admin_immobilier' | 'admin_ville'; cityUuids: string[] }): Promise<string | null> {
        const { data } = await apiClient.post<Feedback<unknown>>('/v1/identity/organization-users/assign-existing', payload);
        return data.flushDescription;
    },

    async revokeRole(assignmentUuid: string): Promise<string | null> {
        const { data } = await apiClient.delete<Feedback<null>>(`/v1/identity/organization-users/${assignmentUuid}`);
        return data.flushDescription;
    },

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

    /** `POST /v1/identity/organization-users/create-admin` — ADMIN_IMMOBILIER ou ADMIN_VILLE (avec ses villes). */
    async createAdmin(payload: CreateAdminPayload): Promise<{ flushDescription: string | null; warnings: Record<string, string> }> {
        const { data } = await apiClient.post<Feedback<unknown>>('/v1/identity/organization-users/create-admin', payload);

        return { flushDescription: data.flushDescription, warnings: data.warnings };
    },

    /** `PUT /v1/identity/users/{uuid}` — édition de la fiche (nom, téléphone). */
    async updateMember(uuid: string, payload: UpdateMemberPayload): Promise<AdministrationMutationResult> {
        const { data } = await apiClient.put<Feedback<AdminApiUser>>(`/v1/identity/users/${uuid}`, payload);

        return { user: data.data, flushDescription: data.flushDescription, warnings: data.warnings };
    },

    /**
     * `PUT /v1/identity/users/{uuid}` avec `isActive: true` — réactivation
     * d'un compte suspendu. Le backend exige `firstName`/`lastName` non vides
     * (groupe `update`) : l'appelant les fournit depuis la fiche courante, et
     * le contrôle d'activation (`ACTIVATE_USER`) est réservé au PATRON.
     */
    async reactivateMember(uuid: string, payload: { firstName: string; lastName: string }): Promise<AdministrationMutationResult> {
        const { data } = await apiClient.put<Feedback<AdminApiUser>>(`/v1/identity/users/${uuid}`, { ...payload, isActive: true });

        return { user: data.data, flushDescription: data.flushDescription, warnings: data.warnings };
    },

    /** `POST /v1/identity/users/{uuid}/suspend` — désactivation + notification email. */
    async suspendMember(uuid: string, payload: SuspendMemberPayload = {}): Promise<AdministrationMutationResult> {
        const { data } = await apiClient.post<Feedback<AdminApiUser>>(`/v1/identity/users/${uuid}/suspend`, payload);

        return { user: data.data, flushDescription: data.flushDescription, warnings: data.warnings };
    },

    /** `GET /v1/identity/organizations/{uuid}` — coordonnées de l'organisation. */
    async fetchOrganization(uuid: string): Promise<OrganizationProfile> {
        const { data } = await apiClient.get<Feedback<OrganizationApiResponse>>(`/v1/identity/organizations/${uuid}`);

        return toProfile(data.data);
    },

    /** `PUT /v1/identity/organizations/{uuid}` — mise à jour des coordonnées (groupe `update`). */
    async updateOrganization(uuid: string, payload: UpdateOrganizationPayload): Promise<OrganizationProfile> {
        const { data } = await apiClient.put<Feedback<OrganizationApiResponse>>(`/v1/identity/organizations/${uuid}`, payload);

        return toProfile(data.data);
    },

    /** `GET /v1/identity/user-cities/user/{uuid}` — villes d'un ADMIN_VILLE. */
    async listUserCities(userUuid: string): Promise<UserCityLink[]> {
        const { data } = await apiClient.get<Feedback<UserCityListData>>(`/v1/identity/user-cities/user/${userUuid}`, {
            params: { page: 1, limit: 100 },
        });

        return data.data.items;
    },

    /** `POST /v1/identity/user-cities` — attribue une ville à un ADMIN_VILLE. */
    async assignUserCity(userUuid: string, cityUuid: string): Promise<void> {
        await apiClient.post<Feedback<unknown>>('/v1/identity/user-cities', { userUuid, cityUuid });
    },

    /** `DELETE /v1/identity/user-cities/{uuid}` — retire une ville (UUID du rattachement). */
    async revokeUserCity(linkUuid: string): Promise<void> {
        await apiClient.delete<Feedback<null>>(`/v1/identity/user-cities/${linkUuid}`);
    },
};
