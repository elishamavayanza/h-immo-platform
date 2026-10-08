import { apiClient } from '../../../../../../services/api/client';
import type { UserSettings } from '../types/settings.types';

export const settingsService = {
    async getMySettings(): Promise<UserSettings> {
        const { data } = await apiClient.get<UserSettings>('/v1/identity/users/me/settings');
        return data;
    },

    async updateMySettings(payload: Partial<UserSettings>): Promise<UserSettings> {
        const { data } = await apiClient.put<UserSettings>('/v1/identity/users/me/settings', payload);
        return data;
    },
};