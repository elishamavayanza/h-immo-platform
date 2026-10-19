import { apiClient } from '../../../../../services/api/client';
import type { Feedback } from '../../../../../services/api/api.types';

export interface UploadedPhotoFile {
    path: string;
    url: string;
}

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

    /**
     * `POST /api/v1/media/parcels/{uuid}/photos` — upload parcel photos (multiple).
     * Returns message in `data.flushDescription`.
     */
    async uploadParcelPhotos(uuid: string, files: File[]): Promise<UploadedPhotoFile[]> {
        const formData = new FormData();
        files.forEach((file) => formData.append('files', file));
        const { data } = await apiClient.post<Feedback<{ uploaded?: UploadedPhotoFile[] }>>(`/v1/media/parcels/${uuid}/photos`, formData);
        return data.data?.uploaded ?? [];
    },

    /**
     * `DELETE /api/v1/media/parcels/{uuid}/photos/{filename}` — delete a parcel photo.
     */
    async deleteParcelPhoto(uuid: string, filename: string): Promise<void> {
        await apiClient.delete<Feedback<unknown>>(`/v1/media/parcels/${uuid}/photos/${filename}`);
    },

    /**
     * `POST /api/v1/units/{uuid}/photos` — upload unit photo (single).
     * Returns message in `data.flushDescription`.
     */
    async uploadUnitPhoto(uuid: string, file: File): Promise<string> {
        const formData = new FormData();
        formData.append('file', file);
        const { data } = await apiClient.post<Feedback<unknown>>(`/v1/units/${uuid}/photos`, formData);
        return data.flushDescription ?? 'Photo ajoutée.';
    },

    /**
     * `DELETE /api/v1/units/{uuid}/photos/{photoUuid}` — delete a unit photo.
     */
    async deleteUnitPhoto(uuid: string, photoUuid: string): Promise<void> {
        await apiClient.delete<Feedback<unknown>>(`/v1/units/${uuid}/photos/${photoUuid}`);
    },

    /**
     * `POST /api/v1/media/organizations/{uuid}/logo` — upload organization logo.
     * Returns the uploaded logo URL in `data.path` and `data.url`.
     */
    async uploadOrgLogo(uuid: string, file: File): Promise<{ path: string; url: string }> {
        const formData = new FormData();
        formData.append('file', file);
        const { data } = await apiClient.post<Feedback<{ path: string; url: string }>>(`/v1/media/organizations/${uuid}/logo`, formData);
        return data.data;
    },

    /**
     * `DELETE /api/v1/media/organizations/{uuid}/logo` — delete organization logo.
     */
    async deleteOrgLogo(uuid: string): Promise<void> {
        await apiClient.delete<Feedback<null>>(`/v1/media/organizations/${uuid}/logo`);
    },

    /**
     * `GET /api/v1/media/parcels/{uuid}/photos` — list parcel photos.
     * Les photos sont retournées dans l'enveloppe standard `Feedback`.
     */
    async fetchParcelPhotos(uuid: string): Promise<string[]> {
        const { data } = await apiClient.get<Feedback<{ photos: string[] }>>(`/v1/media/parcels/${uuid}/photos`);
        return data.data?.photos ?? [];
    },

    /**
     * `GET /api/v1/units/{uuid}/photos` — list unit photos.
     * Les photos sont retournées dans l'enveloppe standard `Feedback`.
     */
    async fetchUnitPhotos(uuid: string): Promise<Array<{ uuid: string; url: string; position: number }>> {
        const { data } = await apiClient.get<Feedback<Array<{ uuid: string; url: string; position: number }>>>(`/v1/units/${uuid}/photos`);
        return data.data ?? [];
    },

    /**
     * `POST /api/v1/media/buildings/{uuid}/photos` — upload building photos (multiple).
     * Returns message in `data.flushDescription`.
     */
    async uploadBuildingPhotos(uuid: string, files: File[]): Promise<UploadedPhotoFile[]> {
        const formData = new FormData();
        files.forEach((file) => formData.append('files', file));
        const { data } = await apiClient.post<Feedback<{ uploaded?: UploadedPhotoFile[] }>>(`/v1/media/buildings/${uuid}/photos`, formData);
        return data.data?.uploaded ?? [];
    },

    /**
     * `DELETE /api/v1/media/buildings/{uuid}/photos/{filename}` — delete a building photo.
     */
    async deleteBuildingPhoto(uuid: string, filename: string): Promise<void> {
        await apiClient.delete<Feedback<unknown>>(`/v1/media/buildings/${uuid}/photos/${filename}`);
    },

    /**
     * `GET /api/v1/media/buildings/{uuid}/photos` — list building photos.
     * Les photos sont retournées dans l'enveloppe standard `Feedback`.
     */
    async fetchBuildingPhotos(uuid: string): Promise<string[]> {
        const { data } = await apiClient.get<Feedback<{ photos: string[] }>>(`/v1/media/buildings/${uuid}/photos`);
        return data.data?.photos ?? [];
    },
};
