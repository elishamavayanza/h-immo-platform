import { apiClient } from '../../../../../../services/api/client';
import type { Feedback } from '../../../../../../services/api/api.types';
import type { UserManual } from '../types/userManual.types';

const CONTENT_URL = '/v1/user-manuals/current/content';

export const userManualService = {
    async getCurrent(): Promise<UserManual> {
        const { data } = await apiClient.get<Feedback<UserManual>>('/v1/user-manuals/current');
        return data.data;
    },
    async getPage(page: number): Promise<Blob> {
        const { data } = await apiClient.getBlob(`/v1/user-manuals/current/pages/${page}`);
        return data;
    },
    async download(): Promise<Blob> {
        const { data } = await apiClient.getBlob(`${CONTENT_URL}?download=1`);
        return data;
    },
};
