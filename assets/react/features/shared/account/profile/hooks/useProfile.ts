import { useCallback, useEffect, useState } from 'react';
import { profileService } from '../services/profileService';
import type { Profile } from '../types/profile.types';

export function useProfile() {
    const [profile, setProfile] = useState<Profile | null>(null);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<unknown>(null);
    const reload = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try { setProfile(await profileService.getProfile()); }
        catch (cause) { setError(cause); }
        finally { setIsLoading(false); }
    }, []);
    useEffect(() => { void reload(); }, [reload]);
    return { profile, isLoading, error, reload };
}
