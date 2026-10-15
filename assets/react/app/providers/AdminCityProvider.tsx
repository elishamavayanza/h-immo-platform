import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';

import { apiClient } from '../../../services/api/client';
import type { Feedback } from '../../../services/api/api.types';
import { useOrganization } from './OrganizationProvider';

export interface AdminCityOption { id: string; name: string; canManagePersonnel: boolean }

interface AdminCityContextValue {
    cities: AdminCityOption[];
    activeCity: AdminCityOption | null;
    activeCityUuid: string;
    isLoading: boolean;
    selectCity: (cityUuid: string) => void;
}

const AdminCityContext = createContext<AdminCityContextValue | null>(null);

/** Ville active de l'ADMIN_VILLE, issue exclusivement de la liste autorisée par l'API. */
export function AdminCityProvider({ children }: { children: ReactNode }) {
    const { currentOrganization, organizationRole } = useOrganization();
    const organizationUuid = currentOrganization?.uuid ?? null;
    const [cities, setCities] = useState<AdminCityOption[]>([]);
    const [activeCityUuid, setActiveCityUuid] = useState('');
    const [isLoading, setIsLoading] = useState(false);

    useEffect(() => {
        let active = true;
        setCities([]);
        setActiveCityUuid('');
        if (!organizationUuid || organizationRole !== 'admin_ville') {
            setIsLoading(false);
            return () => { active = false; };
        }

        setIsLoading(true);
        void apiClient.get<Feedback<{ items: Array<{ id: string; name: string }> }>>('/v1/property/cities', {
            params: { organizationId: organizationUuid, limit: 100 },
        }).then(async ({ data }) => {
            if (!active) return;
            const { data: mine } = await apiClient.get<Feedback<{ items: Array<{ cityId: string; canManagePersonnel: boolean }> }>>('/v1/identity/user-cities/me');
            const permissions = new Map(mine.data.items.map(({ cityId, canManagePersonnel }) => [cityId, canManagePersonnel]));
            const allowedCities = data.data.items.map(({ id, name }) => ({ id, name, canManagePersonnel: permissions.get(id) ?? false }));
            setCities(allowedCities);
            let saved = '';
            try { saved = localStorage.getItem(`h-immo:active-city:${organizationUuid}`) ?? ''; } catch { /* stockage non disponible */ }
            const next = allowedCities.some((city) => city.id === saved) ? saved : allowedCities[0]?.id ?? '';
            setActiveCityUuid(next);
            try {
                if (next) localStorage.setItem(`h-immo:active-city:${organizationUuid}`, next);
                else localStorage.removeItem(`h-immo:active-city:${organizationUuid}`);
            } catch { /* le choix reste actif pour cette visite */ }
        }).catch(() => {
            if (active) { setCities([]); setActiveCityUuid(''); }
        }).finally(() => { if (active) setIsLoading(false); });

        return () => { active = false; };
    }, [organizationUuid, organizationRole]);

    const selectCity = useCallback((cityUuid: string) => {
        if (!cities.some((city) => city.id === cityUuid)) return;
        setActiveCityUuid(cityUuid);
        if (organizationUuid) {
            try { localStorage.setItem(`h-immo:active-city:${organizationUuid}`, cityUuid); } catch { /* le choix reste actif pour cette visite */ }
        }
    }, [cities, organizationUuid]);

    const activeCity = cities.find((city) => city.id === activeCityUuid) ?? null;
    const value = useMemo(() => ({ cities, activeCity, activeCityUuid, isLoading, selectCity }), [cities, activeCity, activeCityUuid, isLoading, selectCity]);
    return <AdminCityContext.Provider value={value}>{children}</AdminCityContext.Provider>;
}

export function useAdminCity(): AdminCityContextValue {
    const context = useContext(AdminCityContext);
    if (!context) throw new Error('useAdminCity doit être utilisé dans un <AdminCityProvider>.');
    return context;
}
