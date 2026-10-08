import { authService } from '../../../../auth/services/authService';
import { mediaService } from '../../../media/services/mediaService';
import type { Profile, ProfileUpdatePayload } from '../types/profile.types';

export const profileService = {
    async getProfile(): Promise<Profile> {
        const { data } = await authService.me();
        return data;
    },

    async updateProfile(uuid: string, payload: ProfileUpdatePayload, currentProfile: Profile): Promise<Profile> {
        const { data } = await authService.updateProfile(uuid, payload);
        return {
            ...currentProfile,
            uuid: data.id,
            email: data.email,
            fullName: data.fullName,
            phone: data.phone,
            profilePhoto: data.profilePhoto,
            platformRole: data.platformRole,
            isActive: data.isActive,
            lastLoginAt: data.lastLoginAt,
        } as Profile;
    },

    async uploadPhoto(uuid: string, file: File): Promise<{ path: string; url: string }> {
        return mediaService.uploadUserPhoto(uuid, file);
    },

    async deletePhoto(uuid: string): Promise<void> {
        await mediaService.deleteUserPhoto(uuid);
    },
};
