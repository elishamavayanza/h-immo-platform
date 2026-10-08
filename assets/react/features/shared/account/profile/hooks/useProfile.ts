import { useCallback, useEffect, useState } from 'react';

import { profileService } from '../services/profileService';
import type { Profile, ProfileUpdatePayload } from '../types/profile.types';

export function useProfile() {
    const [profile, setProfile] = useState<Profile | null>(null);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<unknown>(null);

    const reload = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            setProfile(await profileService.getProfile());
        } catch (cause) {
            setError(cause);
        } finally {
            setIsLoading(false);
        }
    }, []);

    const updateProfile = useCallback(async (payload: ProfileUpdatePayload) => {
        setError(null);
        try {
            const updated = await profileService.updateProfile(payload);
            setProfile(updated);
            return updated;
        } catch (cause) {
            setError(cause);
            throw cause;
        }
    }, []);

    const uploadPhoto = useCallback(async (file: File) => {
        setError(null);
        try {
            const { url } = await profileService.uploadPhoto(file);
            setProfile((prev) => prev ? { ...prev, profilePhoto: url } : null);
            return url;
        } catch (cause) {
            setError(cause);
            throw cause;
        }
    }, []);

    const deletePhoto = useCallback(async () => {
        setError(null);
        try {
            await profileService.deletePhoto();
            setProfile((prev) => prev ? { ...prev, profilePhoto: null } : null);
        } catch (cause) {
            setError(cause);
            throw cause;
        }
    }, []);

    useEffect(() => { void reload(); }, [reload]);

    return { profile, isLoading, error, reload, updateProfile, uploadPhoto, deletePhoto };
}