import { useCallback, useEffect, useMemo, useState } from 'react';

import { useToast } from '../../../../app/layout/MainLayout/contexts/ToastContext';
import { useAuth } from '../../../../app/providers/AuthProvider';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { ApiError } from '../../../../../services/api/api.types';
import { changedFields } from '../../../shared/utils/changedFields';
import { fetchCities } from '../../../admin_immobilier/shared/services/referenceService';
import { administrationService } from '../services/administrationService';
import type { AdminApiUser, AdminCityOption, TeamMember } from '../types/administration.types';

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
    const [cities, setCities] = useState<AdminCityOption[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [search, setSearch] = useState('');
    const [role, setRole] = useState('all');

    const reload = useCallback(async () => {
        if (!organizationUuid) {
            setMembers([]);
            setCities([]);
            setLoading(false);

            return;
        }
        setLoading(true);
        setError(null);
        try {
            const [users, cityPage] = await Promise.all([
                administrationService.list(organizationUuid),
                fetchCities(organizationUuid),
            ]);
            setMembers(users.map((user) => toMember(user, organizationUuid, selfUuid)));
            setCities(cityPage.items.map((city) => ({ uuid: city.id, name: city.name })));
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

    /**
     * `POST /v1/identity/organization-users/create-admin` — le PATRON choisit
     * le rôle ; un ADMIN_VILLE exige au moins une ville (`cityUuids`).
     */
    const createMember = async (input: {
        fullName: string;
        email: string;
        phone: string;
        role: 'admin_immobilier' | 'admin_ville';
        cityUuids?: string[];
    }): Promise<void> => {
        if (!organizationUuid) return;
        try {
            const result = await administrationService.createAdmin({
                organizationUuid,
                role: input.role,
                email: input.email,
                fullName: input.fullName,
                phone: input.phone,
                ...(input.role === 'admin_ville' ? { cityUuids: input.cityUuids ?? [] } : {}),
            });
            push('success', result.flushDescription ?? `Le compte de ${input.fullName} a été créé. Un email de configuration du mot de passe a été envoyé.`);
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
    const updateMember = async (id: string, input: { fullName?: string; phone?: string }): Promise<void> => {
        const member = members.find((entry) => entry.id === id);
        if (!member) return;
        const changes = changedFields({ fullName: input.fullName?.trim(), phone: input.phone?.trim() }, {
            fullName: member.name.trim(),
            phone: member.phone?.trim() ?? '',
        });
        if (Object.keys(changes).length === 0) return;
        const payload: Parameters<typeof administrationService.updateMember>[1] = {};
        if (changes.fullName !== undefined) {
            const [firstName, ...rest] = changes.fullName.split(/\s+/);
            payload.firstName = firstName || '-';
            payload.lastName = rest.join(' ') || '-';
        }
        if (changes.phone !== undefined) payload.phone = changes.phone;
        try {
            const result = await administrationService.updateMember(id, payload);
            if (organizationUuid) {
                setMembers((current) => current.map((member) => member.id === id ? toMember(result.user, organizationUuid, selfUuid) : member));
            }
            push('success', result.flushDescription ?? `Les informations de ${member.name} ont été mises à jour.`);
        } catch (cause) {
            push('error', actionErrorMessage(cause));
            throw cause;
        }
    };

    /** `POST /v1/identity/users/{uuid}/suspend` — motif facultatif, warning si l'email échoue. */
    const suspendMember = async (id: string, reason?: string): Promise<void> => {
        const member = members.find((entry) => entry.id === id);
        try {
            const result = await administrationService.suspendMember(id, reason ? { reason } : {});
            if (organizationUuid) {
                setMembers((current) => current.map((member) => member.id === id ? toMember(result.user, organizationUuid, selfUuid) : member));
            }
            push('success', result.flushDescription ?? `Le compte de ${member?.name ?? 'ce membre'} a été suspendu.`);
            if (result.warnings.emails) {
                push('warning', result.warnings.emails);
            }
        } catch (cause) {
            push('error', actionErrorMessage(cause));
            throw cause;
        }
    };

    /**
     * `PUT /v1/identity/users/{uuid}` avec `isActive: true` — réintègre un
     * compte suspendu. Le backend exige un nom non vide : on reprend celui de
     * la fiche courante (scindé prénom/nom).
     */
    const reactivateMember = async (id: string): Promise<void> => {
        const current = members.find((member) => member.id === id);
        if (!current) return;
        const [firstName, ...rest] = current.name.trim().split(/\s+/);
        try {
            const result = await administrationService.reactivateMember(id, {
                firstName: firstName || '-',
                lastName: rest.join(' ') || '-',
            });
            if (organizationUuid) {
                setMembers((list) => list.map((member) => member.id === id ? toMember(result.user, organizationUuid, selfUuid) : member));
            }
            push('success', result.flushDescription ?? `Le compte de ${current.name} a été réactivé.`);
        } catch (cause) {
            push('error', actionErrorMessage(cause));
            throw cause;
        }
    };

    return {
        members,
        cities,
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
        reactivateMember,
    };
}
