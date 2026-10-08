import { authService } from '../../../../auth/services/authService';
import { mediaService } from '../../../media/services/mediaService';
import type { Profile, ProfileUpdatePayload } from '../types/profile.types';

export const profileService = {
    async getProfile(): Promise<Profile> {
        const { data } = await authService.me();
        return data;
    },

    async updateProfile(payload: ProfileUpdatePayload): Promise<Profile> {
        const { data: currentUser } = await authService.me();
        const updated = await authService.updateProfile(currentUser.uuid, payload);
        return updated;
    },

    async uploadPhoto(file: File): Promise<{ path: string; url: string }> {
        const { data } = await authService.me();
        return mediaService.uploadUserPhoto(data.uuid, file);
    },

    async deletePhoto(): Promise<void> {
        const { data } = await authService.me();
        await mediaService.deleteUserPhoto(data.uuid);
    },
};