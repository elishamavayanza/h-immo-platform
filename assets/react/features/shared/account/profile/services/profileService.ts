import { authService } from '../../../../auth/services/authService';
import type { Profile } from '../types/profile.types';

export const profileService = {
    async getProfile(): Promise<Profile> {
        const { data } = await authService.me();
        return data;
    },
};
