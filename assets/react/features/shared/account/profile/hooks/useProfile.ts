import { useCallback, useEffect, useState } from 'react';

import { useToast } from '../../../../../app/layout/MainLayout/contexts/ToastContext';
import { useAuth } from '../../../../../app/providers/AuthProvider';
import { ApiError } from '../../../../../../services/api/api.types';
import { profileService } from '../services/profileService';
import type { Profile, ProfileUpdatePayload } from '../types/profile.types';

function errorMessage(cause: unknown): string {
    return cause instanceof ApiError ? cause.message : 'Une erreur inattendue est survenue. Réessayez.';
}

export function useProfile() {
    const { push } = useToast();
    const { updateUser } = useAuth();
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
            updateUser({ fullName: updated.fullName, email: updated.email, profilePhoto: updated.profilePhoto });
            push('success', 'Vos informations de profil ont été enregistrées.');
            return updated;
        } catch (cause) {
            push('error', errorMessage(cause));
            throw cause;
        }
    }, [profile, push, updateUser]);

    const uploadPhoto = useCallback(async (file: File) => {
        if (!profile) return;
        try {
            const { path } = await profileService.uploadPhoto(profile.uuid, file);
            setProfile((prev) => prev ? { ...prev, profilePhoto: path } : null);
            updateUser({ profilePhoto: path });
            push('success', 'Photo de profil mise à jour.');
            return path;
        } catch (cause) {
            push('error', errorMessage(cause));
            throw cause;
        }
    }, [profile, push, updateUser]);

    const deletePhoto = useCallback(async () => {
        if (!profile) return;
        try {
            await profileService.deletePhoto(profile.uuid);
            setProfile((prev) => prev ? { ...prev, profilePhoto: null } : null);
            updateUser({ profilePhoto: null });
            push('success', 'Photo de profil supprimée.');
        } catch (cause) {
            push('error', errorMessage(cause));
            throw cause;
        }
    }, [profile, push, updateUser]);

    return { profile, isLoading, error, reload, updateProfile, uploadPhoto, deletePhoto };
}
