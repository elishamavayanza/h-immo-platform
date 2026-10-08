import { apiClient } from '../../../../../services/api/client';
import type { Feedback } from '../../../../../services/api/api.types';

/** Résout les chemins média renvoyés par l'API en URL utilisable côté navigateur. */
export function mediaHref(path: string | null | undefined): string | null {
    if (!path) return null;
    if (/^(https?:)?\/\//.test(path) || path.startsWith('/') || path.startsWith('data:')) return path;
    return `/uploads/${path}`;
}

export const mediaService = {
    /**
     * `POST /api/v1/media/users/{uuid}/photo` — upload user profile photo.
     * Returns the uploaded photo URL in `data.path` and `data.url`.
     */
    async uploadUserPhoto(uuid: string, file: File): Promise<{ path: string; url: string }> {
        const formData = new FormData();
        formData.append('file', file);
        const { data } = await apiClient.post<Feedback<{ path: string; url: string }>>(`/v1/media/users/${uuid}/photo`, formData);
        return data.data;
    },

    /**
     * `DELETE /api/v1/media/users/{uuid}/photo` — delete user profile photo.
     */
    async deleteUserPhoto(uuid: string): Promise<void> {
        await apiClient.delete<Feedback<null>>(`/v1/media/users/${uuid}/photo`);
    },
};
