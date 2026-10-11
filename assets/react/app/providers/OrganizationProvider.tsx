// ============================================================
// upload/react/app/providers/OrganizationProvider.tsx
// OrganizationContext — l'ORGANIZATION ACTIVE et son rôle.
//
// Le rôle métier n'est JAMAIS déduit d'un cache client : à chaque
// sélection d'organization (et au chargement initial), il est re-résolu
// par `GET /api/v1/identity/organizations/{uuid}/membership`, qui répond
// le rôle de l'appelant POUR l'organization demandée (403 si l'adhésion
// a été révoquée). `user.organizations` (de `/auth/me`) ne sert qu'à
// présenter les choix possibles, pas à autoriser.
//
// Un SUPER_ADMIN n'a pas d'organization active : il affiche le menu
// plateforme, sans sélecteur d'organization métier.
// ============================================================

import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';

import { apiClient } from '../../../services/api/client';
import { ApiError, type Feedback, type OrganizationRole, type PlatformRole, type SessionOrganizationMembership } from '../../../services/api/api.types';
import { useAuth } from './AuthProvider';

const ACTIVE_ORGANIZATION_KEY = 'h-immo:active-organization';

interface OrganizationContextValue {
    /** Organization active (null pour un SUPER_ADMIN ou sans appartenance). */
    currentOrganization: SessionOrganizationMembership | null;
    /** Rôle métier résolu pour l'organization active, via l'API. */
    organizationRole: OrganizationRole | null;
    /** Rôle plateforme (`super_admin`) ou null pour un compte métier. */
    platformRole: PlatformRole | null;
    /** Vrai pendant la résolution initiale du rôle (premier chargement). */
    isLoading: boolean;
    /**
     * Bascule l'organization active : `switchOrganization(uuid)` re-résout
     * le rôle côté backend avant de modifier l'état. Un échec (adhésion
     * révoquée) bascule sur la première appartenance restante, ou vide le
     * contexte si plus aucune n'est valable.
     */
    switchOrganization: (uuid: string) => Promise<void>;
}

const OrganizationContext = createContext<OrganizationContextValue | undefined>(undefined);

/**
 * Résout le rôle de l'appelant POUR une organization via le nouvel endpoint
 * dédié (miroir front de `SessionOrganizationMembership`).
 */
async function resolveMembership(uuid: string): Promise<SessionOrganizationMembership> {
    const { data } = await apiClient.get<Feedback<SessionOrganizationMembership>>(
        `/v1/identity/organizations/${uuid}/membership`,
    );

    return data.data;
}

async function resolveFirstAvailableMembership(
    memberships: SessionOrganizationMembership[],
): Promise<SessionOrganizationMembership | null> {
    for (const membership of memberships) {
        try {
            return await resolveMembership(membership.uuid);
        } catch {
            // L'appartenance a pu être révoquée depuis le dernier chargement de session.
        }
    }

    return null;
}

function likelyRevoked(error: unknown): boolean {
    return error instanceof ApiError && (error.status === 403 || error.status === 404);
}

export function OrganizationProvider({ children }: { children: ReactNode }) {
    const { user, isAuthenticated, isLoading: isAuthLoading } = useAuth();

    const [currentOrganization, setCurrentOrganization] = useState<SessionOrganizationMembership | null>(null);
    const [organizationRole, setOrganizationRole] = useState<OrganizationRole | null>(null);
    const [platformRole, setPlatformRole] = useState<PlatformRole | null>(null);
    const [isLoading, setIsLoading] = useState(true);
    const [resolvedUserUuid, setResolvedUserUuid] = useState<string | null>(null);

    const fallbackToFirstValid = useCallback(async (): Promise<void> => {
        const resolved = await resolveFirstAvailableMembership(user?.organizations ?? []);
        if (resolved) {
            setCurrentOrganization(resolved);
            setOrganizationRole(resolved.role);
            localStorage.setItem(ACTIVE_ORGANIZATION_KEY, resolved.uuid);

            return;
        }

        localStorage.removeItem(ACTIVE_ORGANIZATION_KEY);
        setCurrentOrganization(null);
        setOrganizationRole(null);
    }, [user]);

    // Résolution initiale : rôle re-résolu par l'API (jamais pris sur
    // `user.organizations`), ORGANIZATION choisie = appartenance persistée
    // si elle existe encore, sinon la première.
    useEffect(() => {
        if (isAuthLoading) return;

        let cancelled = false;

        if (!isAuthenticated || !user) {
            setCurrentOrganization(null);
            setOrganizationRole(null);
            setPlatformRole(null);
            setResolvedUserUuid(null);
            setIsLoading(false);

            return () => { cancelled = true; };
        }

        setIsLoading(true);

        if (user.platformRole === 'super_admin') {
            setPlatformRole('super_admin');
            setCurrentOrganization(null);
            setOrganizationRole(null);
            setResolvedUserUuid(user.uuid);
            setIsLoading(false);

            return () => { cancelled = true; };
        }

        const memberships = user.organizations;
        if (memberships.length === 0) {
            setCurrentOrganization(null);
            setOrganizationRole(null);
            setPlatformRole(null);
            setResolvedUserUuid(user.uuid);
            setIsLoading(false);

            return () => { cancelled = true; };
        }

        const persisted = localStorage.getItem(ACTIVE_ORGANIZATION_KEY);
        const target =
            memberships.find(m => m.uuid === persisted)?.uuid
            ?? memberships[0].uuid;

        void (async () => {
            try {
                const resolved = await resolveMembership(target);
                if (cancelled) return;
                setCurrentOrganization(resolved);
                setOrganizationRole(resolved.role);
                setPlatformRole(null);
                localStorage.setItem(ACTIVE_ORGANIZATION_KEY, resolved.uuid);
            } catch {
                const fallback = await resolveFirstAvailableMembership(memberships);
                if (cancelled) return;
                if (fallback) {
                    setCurrentOrganization(fallback);
                    setOrganizationRole(fallback.role);
                    setPlatformRole(null);
                    localStorage.setItem(ACTIVE_ORGANIZATION_KEY, fallback.uuid);
                } else {
                    localStorage.removeItem(ACTIVE_ORGANIZATION_KEY);
                    setCurrentOrganization(null);
                    setOrganizationRole(null);
                    setPlatformRole(null);
                }
            } finally {
                if (!cancelled) {
                    setResolvedUserUuid(user.uuid);
                    setIsLoading(false);
                }
            }
        })();

        return () => { cancelled = true; };
    }, [isAuthLoading, isAuthenticated, user]);

    // Bascule dans le menu d'une autre organization : on conserve l'état
    // courant tant que la nouvelle adhésion n'est pas confirmée par l'API.
    const switchOrganization = useCallback(async (uuid: string) => {
        if (!user) return;

        try {
            const resolved = await resolveMembership(uuid);
            setCurrentOrganization(resolved);
            setOrganizationRole(resolved.role);
            setPlatformRole(null);
            localStorage.setItem(ACTIVE_ORGANIZATION_KEY, resolved.uuid);
        } catch (error) {
            if (likelyRevoked(error)) {
                await fallbackToFirstValid();
            }
        }
    }, [user, fallbackToFirstValid]);

    const isUserResolutionPending = isAuthenticated && user !== null && resolvedUserUuid !== user.uuid;

    const value = useMemo<OrganizationContextValue>(() => ({
        currentOrganization,
        organizationRole,
        platformRole,
        isLoading: isLoading || isUserResolutionPending,
        switchOrganization,
    }), [currentOrganization, organizationRole, platformRole, isLoading, isUserResolutionPending, switchOrganization]);

    return <OrganizationContext.Provider value={value}>{children}</OrganizationContext.Provider>;
}

export function useOrganization(): OrganizationContextValue {
    const context = useContext(OrganizationContext);
    if (!context) {
        throw new Error('useOrganization doit être utilisé dans un <OrganizationProvider>.');
    }
    return context;
}
