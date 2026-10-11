/**
 * publicSettingsService — Réglages publics de la vitrine (espace PATRON).
 *
 * Contrat HTTP :
 *  - `GET /api/v1/identity/organizations/{uuid}` → `OrganizationResponse`
 *    (maintenant enrichi de `slug`, `publicDescription`, `isPubliclyListed`).
 *    Borné par `checkOrganizationAccess(VIEW_ORGANIZATION)`.
 *  - `PATCH /api/v1/identity/organizations/{uuid}/showcase` →
 *    `OrganizationShowcaseRequest`, réservé au PATRON
 *    (`MANAGE_ORGANIZATION`), 409 si le slug est déjà pris.
 *  - `POST|DELETE /api/v1/media/organizations/{uuid}/logo` → logo.
 *
 * Toute réponse transite par l'enveloppe `Feedback` ; le `apiClient` a
 * `baseURL = '/api'`, donc les `url` commencent à `/v1/...`.
 */
import { apiClient } from '../../../../../services/api/client';
import type { Feedback } from '../../../../../services/api/api.types';
import { mediaHref } from '../../../shared/media/services/mediaService';
import type { OrganizationPublicSettings, ShowcasePayload } from '../types/publicSettings.types';

/** Forme du `OrganizationResponse` renvoyé par l'API (champs utilisés ici). */
interface OrganizationApiResponse {
    id: string;
    name: string;
    slug: string | null;
    publicDescription: string | null;
    isPubliclyListed: boolean;
    logo: string | null;
}

function toSettings(response: OrganizationApiResponse): OrganizationPublicSettings {
    return {
        uuid: response.id,
        name: response.name,
        slug: response.slug,
        publicDescription: response.publicDescription,
        isPubliclyListed: response.isPubliclyListed,
        logo: response.logo,
    };
}

/** `GET …/organizations/{uuid}` — réglages publics courants de l'organisation. */
export async function fetchPublicSettings(uuid: string): Promise<OrganizationPublicSettings> {
    const { data } = await apiClient.get<Feedback<OrganizationApiResponse>>(`/v1/identity/organizations/${uuid}`);

    return toSettings(data.data);
}

/** `PATCH …/organizations/{uuid}/showcase` — met à jour les réglages publics. */
export async function updateShowcase(uuid: string, payload: ShowcasePayload): Promise<OrganizationPublicSettings> {
    const { data } = await apiClient.patch<Feedback<OrganizationApiResponse>>(`/v1/identity/organizations/${uuid}/showcase`, payload);

    return toSettings(data.data);
}

/** `POST …/media/organizations/{uuid}/logo` — remplace le logo. Renvoie l'URL publique. */
export async function uploadOrganizationLogo(uuid: string, file: File): Promise<string> {
    const formData = new FormData();
    formData.append('file', file);
    const { data } = await apiClient.post<Feedback<{ path: string; url: string }>>(`/v1/media/organizations/${uuid}/logo`, formData);

    return data.data.url;
}

/** `DELETE …/media/organizations/{uuid}/logo` — retire le logo. */
export async function deleteOrganizationLogo(uuid: string): Promise<void> {
    await apiClient.delete<Feedback<null>>(`/v1/media/organizations/${uuid}/logo`);
}

/** Résout une URL d'image affichable depuis la valeur `logo` du DTO. */
export function logoHref(logo: string | null): string | null {
    return mediaHref(logo);
}
