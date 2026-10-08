import { apiClient } from '../../../../../services/api/client';
import type { Feedback } from '../../../../../services/api/api.types';
import type {
    OrganizationApiResponse,
    OrganizationCreatePayload,
    OrganizationCreateResult,
    OrganizationListData,
    OrganizationRow,
    OrganizationTransitionResult,
    OrganizationUpdatePayload,
} from '../types/organization.types';

/**
 * organisationsService
 *
 * Contrat HTTP des endpoints organisations (`src/Controller/Api/Identity/OrganizationController.php`).
 * Toute réponse transite par l'enveloppe `Feedback` : `data` porte soit un
 * `OrganizationResponse` (create/update/show), soit un objet paginé
 * `{ items, total, page, limit }` (list).
 *
 * Les routes API commencent par `/api/v1/identity/organizations` ; le
 * `apiClient` a `baseURL = '/api'`, donc `url` commence à `/v1/...`.
 */
export const organizationsService = {
    /** `GET /api/v1/identity/organizations` — liste paginée (SUPER_ADMIN : toutes). */
    async list(params?: { page?: number; limit?: number; search?: string }): Promise<OrganizationRow[]> {
        const query: Record<string, string | number> = {
            page: params?.page ?? 1,
            limit: params?.limit ?? 100,
            ...(params?.search ? { search: params.search } : {}),
        };
        const { data } = await apiClient.get<Feedback<OrganizationListData>>('/v1/identity/organizations', { params: query });
        return data.data.items.map(toRow);
    },

    /**
     * `POST /api/v1/identity/organizations` — crée le tenant ET son PATRON
     * dans une unique transaction. Retourne le feedback backend (message de
     * flush + avertissements, ex. email de configuration non envoyé).
     * `logoFile`, s'il est présent, est uploadé séparément après la création
     * par `uploadLogo()` : le tenant n'existe pas encore au moment où on
     * POSTe, le logo ne peut pas lui être rattaché dans la même requête.
     */
    async create(payload: OrganizationCreatePayload): Promise<OrganizationCreateResult> {
        const { logoFile: _logoFile, ...body } = payload;
        const { data } = await apiClient.post<Feedback<OrganizationApiResponse>>('/v1/identity/organizations', body);
        return {
            organization: toRow(data.data),
            flushDescription: data.flushDescription,
            warnings: data.warnings,
        };
    },

    /** `PUT|PATCH /api/v1/identity/organizations/{uuid}` — mise à jour partielle. */
    async update(uuid: string, payload: OrganizationUpdatePayload): Promise<OrganizationRow> {
        const { logoFile: _logoFile, removeLogo: _removeLogo, ...body } = payload;
        const { data } = await apiClient.put<Feedback<OrganizationApiResponse>>(`/v1/identity/organizations/${uuid}`, body);
        return toRow(data.data);
    },

    /** `DELETE /api/v1/identity/organizations/{uuid}` — suppression logique (soft delete). */
    async remove(uuid: string): Promise<void> {
        await apiClient.delete<Feedback<null>>(`/v1/identity/organizations/${uuid}`);
    },

    /**
     * `POST /api/v1/media/organizations/{uuid}/logo` — upload multipart du
     * logo. Renvoie l'URL publique du fichier (`/uploads/organizations/...`).
     */
    async uploadLogo(uuid: string, file: File): Promise<string> {
        const formData = new FormData();
        formData.append('file', file);
        const { data } = await apiClient.post<Feedback<{ path: string; url: string }>>(`/v1/media/organizations/${uuid}/logo`, formData);
        return data.data.url;
    },

    /** `DELETE /api/v1/media/organizations/{uuid}/logo` — retire le logo du tenant. */
    async deleteLogo(uuid: string): Promise<void> {
        await apiClient.delete<Feedback<null>>(`/v1/media/organizations/${uuid}/logo`);
    },

    /**
     * `POST /api/v1/identity/organizations/{uuid}/suspend` — suspend le tenant
     * avec un motif obligatoire. Le backend archive le motif dans l'audit et
     * notifie les membres par email (échecs non bloquants → `warnings`).
     */
    async suspend(uuid: string, reason: string): Promise<OrganizationTransitionResult> {
        const { data } = await apiClient.post<Feedback<OrganizationApiResponse>>(`/v1/identity/organizations/${uuid}/suspend`, { reason });
        return {
            organization: toRow(data.data),
            flushDescription: data.flushDescription,
            warnings: data.warnings,
        };
    },

    /** `POST /api/v1/identity/organizations/{uuid}/reactivate` — rétablit un tenant suspendu. */
    async reactivate(uuid: string): Promise<OrganizationTransitionResult> {
        const { data } = await apiClient.post<Feedback<OrganizationApiResponse>>(`/v1/identity/organizations/${uuid}/reactivate`);
        return {
            organization: toRow(data.data),
            flushDescription: data.flushDescription,
            warnings: data.warnings,
        };
    },
};

/**
 * Projette le DTO backend `OrganizationResponse` vers la ligne du tableau.
 * Séparation réponse API / affichage : la page n'a jamais à connaître le DTO.
 */
function toRow(response: OrganizationApiResponse): OrganizationRow {
    return {
        id: response.id,
        name: response.name,
        code: response.code,
        email: response.email,
        phone: response.phone,
        address: response.address ?? '',
        city: response.city ?? '',
        logo: response.logo,
        status: response.status,
        createdAt: response.createdAt,
    };
}

/**
 * Résout une URL d'image utilisable dans `<img src>` à partir de la valeur
 * `logo` du DTO : l'API renvoie le chemin relatif (`organizations/…`), la
 * média renvoie une URL publique (`/uploads/organizations/…`).
 */
export function logoHref(logo: string | null): string | null {
    if (!logo) return null;
    if (/^(https?:)?\/\//.test(logo) || logo.startsWith('/')) return logo;
    return `/uploads/${logo}`;
}