import { apiClient } from '../../../../../services/api/client';
import type { Feedback } from '../../../../../services/api/api.types';
import type { Publication, PublicationDraft } from '../types/publication.types';

export const publicationsService = {
    async management(): Promise<Publication[]> {
        const { data } = await apiClient.get<Feedback<Publication[]>>('/v1/publications/management');
        return data.data;
    },
    async active(): Promise<Publication[]> {
        const { data } = await apiClient.get<Feedback<Publication[]>>('/v1/publications/active');
        return data.data;
    },
    async saveDraft(payload: PublicationDraft, uuid?: string): Promise<Publication> {
        const { data } = uuid
            ? await apiClient.put<Feedback<Publication>>(`/v1/publications/${uuid}`, payload)
            : await apiClient.post<Feedback<Publication>>('/v1/publications', payload);
        return data.data;
    },
    async publish(uuid: string): Promise<Publication> {
        const { data } = await apiClient.post<Feedback<Publication>>(`/v1/publications/${uuid}/publish`);
        return data.data;
    },
    async archive(uuid: string): Promise<void> { await apiClient.post(`/v1/publications/${uuid}/archive`); },
    async withdraw(uuid: string): Promise<void> { await apiClient.post(`/v1/publications/${uuid}/withdraw`); },
    async uploadImages(uuid: string, files: File[], captions: string[]): Promise<Publication> {
        const form = new FormData();
        files.forEach((file, index) => { form.append('files[]', file); form.append('captions[]', captions[index] ?? ''); });
        const { data } = await apiClient.post<Feedback<Publication>>(`/v1/publications/${uuid}/images`, form);
        return data.data;
    },
    async open(uuid: string): Promise<Publication> {
        const { data } = await apiClient.get<Feedback<Publication>>(`/v1/publications/${uuid}`);
        return data.data;
    },
};
