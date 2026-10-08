import { useCallback, useEffect, useState } from 'react';

import { useToast } from '../../../../../app/layout/MainLayout/contexts/ToastContext';
import { ApiError } from '../../../../../../services/api/api.types';
import { profileService } from '../services/profileService';
import type { Profile, ProfileUpdatePayload } from '../types/profile.types';

function errorMessage(cause: unknown): string {
    return cause instanceof ApiError ? cause.message : 'Une erreur inattendue est survenue. Réessayez.';
}

export function useProfile() {
    const { push } = useToast();
    const [profile, setProfile] = useState<Profile | null>(null);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<Error | null>(null);

    const reload = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            setProfile(await profileService.getProfile());
        } catch (cause) {
            setError(cause instanceof Error ? cause : new Error(errorMessage(cause)));
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => { void reload(); }, [reload]);

    const updateProfile = useCallback(async (payload: ProfileUpdatePayload) => {
        if (!profile) return;
        try {
            const updated = await profileService.updateProfile(profile.uuid, payload, profile);
            setProfile(updated);
            push('success', 'Profil mis à jour.');
            return updated;
        } catch (cause) {
            push('error', errorMessage(cause));
            throw cause;
        }
    }, [profile]);

    const uploadPhoto = useCallback(async (file: File) => {
        if (!profile) return;
        try {
            const { url } = await profileService.uploadPhoto(profile.uuid, file);
            setProfile((prev) => prev ? { ...prev, profilePhoto: url } : null);
            push('success', 'Photo de profil mise à jour.');
            return url;
        } catch (cause) {
            push('error', errorMessage(cause));
            throw cause;
        }
    }, [profile]);

    const deletePhoto = useCallback(async () => {
        if (!profile) return;
        try {
            await profileService.deletePhoto(profile.uuid);
            setProfile((prev) => prev ? { ...prev, profilePhoto: null } : null);
            push('success', 'Photo de profil supprimée.');
        } catch (cause) {
            push('error', errorMessage(cause));
            throw cause;
        }
    }, [profile]);

    return { profile, isLoading, error, reload, updateProfile, uploadPhoto, deletePhoto };
}