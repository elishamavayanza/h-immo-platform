import { useCallback, useEffect, useMemo, useState } from 'react';

import { useToast } from '../../../../app/layout/MainLayout/contexts/ToastContext';
import { useAuth } from '../../../../app/providers/AuthProvider';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { ApiError } from '../../../../../services/api/api.types';
import { administrationService } from '../services/administrationService';
import type { AdminApiUser, TeamMember } from '../types/administration.types';

function actionErrorMessage(cause: unknown): string {
    return cause instanceof ApiError ? cause.message : 'Une erreur inattendue est survenue. Réessayez.';
}

/** Projette `UserResponse` (filtré sur l'organization active) vers une ligne du tableau. */
function toMember(user: AdminApiUser, organizationId: string, selfUuid: string | null): TeamMember {
    const membership = user.memberships?.find((entry) => entry.organizationId === organizationId) ?? user.memberships?.[0];

    return {
        id: user.id,
        name: user.fullName,
        email: user.email,
        phone: user.phone ?? null,
        role: membership?.role ?? 'admin_immobilier',
        status: user.isActive ? 'active' : 'inactive',
        isSelf: selfUuid !== null && user.id === selfUuid,
    };
}

export function useAdministration() {
    const { push } = useToast();
    const { currentOrganization } = useOrganization();
    const { user } = useAuth();

    const organizationUuid = currentOrganization?.uuid ?? null;
    const selfUuid = user?.uuid ?? null;

    const [members, setMembers] = useState<TeamMember[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [search, setSearch] = useState('');
    const [role, setRole] = useState('all');

    const reload = useCallback(async () => {
        if (!organizationUuid) {
            setMembers([]);
            setLoading(false);

            return;
        }
        setLoading(true);
        setError(null);
        try {
            const users = await administrationService.list(organizationUuid);
            setMembers(users.map((user) => toMember(user, organizationUuid, selfUuid)));
        } catch (cause) {
            setError(cause instanceof ApiError ? cause.message : 'Impossible de charger les membres de l’équipe.');
        } finally {
            setLoading(false);
        }
    }, [organizationUuid, selfUuid]);

    useEffect(() => { void reload(); }, [reload]);

    const rows = useMemo(() => members.filter((member) => {
        const query = search.trim().toLocaleLowerCase('fr');

        return (!query || [member.name, member.email].some((value) => value.toLocaleLowerCase('fr').includes(query)))
            && (role === 'all' || member.role === role);
    }), [members, search, role]);

    /** `POST /v1/identity/organization-users/create-admin` — rôle fixé à ADMIN_IMMOBILIER. */
    const createMember = async (input: { fullName: string; email: string; phone: string }): Promise<void> => {
        if (!organizationUuid) return;
        try {
            const result = await administrationService.createAdmin({
                organizationUuid,
                role: 'admin_immobilier',
                email: input.email,
                fullName: input.fullName,
                phone: input.phone,
            });
            push('success', result.flushDescription ?? 'Le membre a été créé. Un email de configuration du mot de passe a été envoyé.');
            if (result.warnings.email) {
                push('warning', result.warnings.email);
            }
            await reload();
        } catch (cause) {
            push('error', actionErrorMessage(cause));
            throw cause;
        }
    };

    /** `PUT /v1/identity/users/{uuid}` — le backend attend firstName/lastName séparés. */
    const updateMember = async (id: string, input: { fullName: string; phone: string }): Promise<void> => {
        const [firstName, ...rest] = input.fullName.trim().split(/\s+/);
        try {
            const result = await administrationService.updateMember(id, {
                firstName: firstName || '-',
                lastName: rest.join(' ') || '-',
                phone: input.phone,
            });
            if (organizationUuid) {
                setMembers((current) => current.map((member) => member.id === id ? toMember(result.user, organizationUuid, selfUuid) : member));
            }
            push('success', result.flushDescription ?? 'Le membre a été mis à jour.');
        } catch (cause) {
            push('error', actionErrorMessage(cause));
            throw cause;
        }
    };

    /** `POST /v1/identity/users/{uuid}/suspend` — motif facultatif, warning si l'email échoue. */
    const suspendMember = async (id: string, reason?: string): Promise<void> => {
        try {
            const result = await administrationService.suspendMember(id, reason ? { reason } : {});
            if (organizationUuid) {
                setMembers((current) => current.map((member) => member.id === id ? toMember(result.user, organizationUuid, selfUuid) : member));
            }
            push('success', result.flushDescription ?? 'Le compte a été suspendu.');
            if (result.warnings.emails) {
                push('warning', result.warnings.emails);
            }
        } catch (cause) {
            push('error', actionErrorMessage(cause));
            throw cause;
        }
    };

    return {
        members,
        rows,
        loading,
        error,
        reload,
        search,
        setSearch,
        role,
        setRole,
        createMember,
        updateMember,
        suspendMember,
    };
}