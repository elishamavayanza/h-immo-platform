import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';

import { apiClient } from '../../../services/api/client';
import { ApiError, type Feedback, type OrganizationRole, type PlatformRole, type SessionOrganizationMembership } from '../../../services/api/api.types';
import { useAuth } from './AuthProvider';

const ACTIVE_WORKSPACE_KEY = 'h-immo:active-workspace';

interface ActiveWorkspace { uuid: string; role: OrganizationRole }
interface OrganizationContextValue {
    currentOrganization: SessionOrganizationMembership | null;
    organizationRole: OrganizationRole | null;
    platformRole: PlatformRole | null;
    workspaces: SessionOrganizationMembership[];
    isLoading: boolean;
    isSwitching: boolean;
    switchWorkspace: (workspace: SessionOrganizationMembership) => Promise<void>;
}

const OrganizationContext = createContext<OrganizationContextValue | undefined>(undefined);

function readActiveWorkspace(): ActiveWorkspace | null {
    try {
        const value = localStorage.getItem(ACTIVE_WORKSPACE_KEY);
        if (value) return JSON.parse(value) as ActiveWorkspace;
        // Migration douce de l'ancienne préférence qui ne mémorisait que l'organisation.
        const oldOrganization = localStorage.getItem('h-immo:active-organization');
        return oldOrganization ? { uuid: oldOrganization, role: 'patron' } : null;
    } catch {
        return null;
    }
}

function persistWorkspace(workspace: SessionOrganizationMembership): void {
    localStorage.setItem(ACTIVE_WORKSPACE_KEY, JSON.stringify({ uuid: workspace.uuid, role: workspace.role }));
    localStorage.removeItem('h-immo:active-organization');
}

async function resolveMembership(workspace: SessionOrganizationMembership): Promise<SessionOrganizationMembership> {
    const { data } = await apiClient.get<Feedback<SessionOrganizationMembership>>(
        `/v1/identity/organizations/${workspace.uuid}/membership`,
        { params: { role: workspace.role } },
    );
    return data.data;
}

function workspaceKey(workspace: SessionOrganizationMembership): string {
    return `${workspace.uuid}:${workspace.role}`;
}

function isRevoked(error: unknown): boolean {
    return error instanceof ApiError && (error.status === 403 || error.status === 404);
}

export function OrganizationProvider({ children }: { children: ReactNode }) {
    const { user, isAuthenticated, isLoading: isAuthLoading } = useAuth();
    const [currentOrganization, setCurrentOrganization] = useState<SessionOrganizationMembership | null>(null);
    const [organizationRole, setOrganizationRole] = useState<OrganizationRole | null>(null);
    const [platformRole, setPlatformRole] = useState<PlatformRole | null>(null);
    const [isLoading, setIsLoading] = useState(true);
    const [isSwitching, setIsSwitching] = useState(false);
    const [resolvedUserUuid, setResolvedUserUuid] = useState<string | null>(null);
    const workspaces = useMemo(() => {
        const unique = new Map<string, SessionOrganizationMembership>();
        for (const membership of user?.organizations ?? []) unique.set(workspaceKey(membership), membership);
        return [...unique.values()].sort((a, b) => a.name.localeCompare(b.name) || a.role.localeCompare(b.role));
    }, [user?.organizations]);
    const workspaceSignature = workspaces.map(workspaceKey).join('|');

    const fallback = useCallback(async (memberships: SessionOrganizationMembership[]) => {
        for (const candidate of memberships) {
            try {
                const verified = await resolveMembership(candidate);
                setCurrentOrganization(verified);
                setOrganizationRole(verified.role);
                persistWorkspace(verified);
                return;
            } catch {
                // L'appartenance a pu être révoquée depuis le dernier /auth/me.
            }
        }
        localStorage.removeItem(ACTIVE_WORKSPACE_KEY);
        localStorage.removeItem('h-immo:active-organization');
        setCurrentOrganization(null);
        setOrganizationRole(null);
    }, []);

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
        const candidates = workspaces;
        if (candidates.length === 0) {
            setCurrentOrganization(null);
            setOrganizationRole(null);
            setPlatformRole(null);
            setResolvedUserUuid(user.uuid);
            setIsLoading(false);
            return () => { cancelled = true; };
        }
        const persisted = readActiveWorkspace();
        const target = candidates.find(item => item.uuid === persisted?.uuid && item.role === persisted.role) ?? candidates[0];
        void (async () => {
            try {
                const verified = await resolveMembership(target);
                if (cancelled) return;
                setCurrentOrganization(verified);
                setOrganizationRole(verified.role);
                setPlatformRole(null);
                persistWorkspace(verified);
            } catch {
                if (cancelled) return;
                await fallback(candidates);
                setPlatformRole(null);
            } finally {
                if (!cancelled) {
                    setResolvedUserUuid(user.uuid);
                    setIsLoading(false);
                }
            }
        })();
        return () => { cancelled = true; };
    }, [isAuthLoading, isAuthenticated, user?.uuid, workspaceSignature, fallback]);

    const switchWorkspace = useCallback(async (workspace: SessionOrganizationMembership) => {
        setIsSwitching(true);
        try {
            const verified = await resolveMembership(workspace);
            persistWorkspace(verified);
            setCurrentOrganization(verified);
            setOrganizationRole(verified.role);
            setPlatformRole(null);
        } catch (error) {
            if (isRevoked(error)) await fallback(workspaces);
            throw error;
        } finally {
            setIsSwitching(false);
        }
    }, [fallback, workspaces]);

    const value = useMemo<OrganizationContextValue>(() => ({
        currentOrganization,
        organizationRole,
        platformRole,
        workspaces,
        isLoading: isLoading || (isAuthenticated && user !== null && resolvedUserUuid !== user.uuid),
        isSwitching,
        switchWorkspace,
    }), [currentOrganization, organizationRole, platformRole, workspaces, isLoading, isAuthenticated, user, resolvedUserUuid, isSwitching, switchWorkspace]);

    return <OrganizationContext.Provider value={value}>{children}</OrganizationContext.Provider>;
}

export function useOrganization(): OrganizationContextValue {
    const context = useContext(OrganizationContext);
    if (!context) throw new Error('useOrganization doit être utilisé dans un <OrganizationProvider>.');
    return context;
}
