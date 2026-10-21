import { useCallback, useEffect, useMemo, useState } from 'react';

import { useToast } from '../../../../app/layout/MainLayout/contexts/ToastContext';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { ApiError, type OrganizationRole } from '../../../../../services/api/api.types';
import { fetchCities } from '../../../admin_immobilier/shared/services/referenceService';
import { administrationService } from '../services/administrationService';
import type { AdminCityOption, TeamMember } from '../types/administration.types';

function actionErrorMessage(cause: unknown): string {
    return cause instanceof ApiError ? cause.message : 'Une erreur inattendue est survenue. Réessayez.';
}

/**
 * L’API expose au PATRON une lecture dédiée des affectations. Les endpoints
 * génériques d’administration des comptes exigent MANAGE_USERS et ne lui sont
 * pas ouverts ; cette page ne doit ni les appeler ni présenter leurs actions.
 */
export function useAdministration() {
    const { push } = useToast();
    const { currentOrganization } = useOrganization();
    const organizationUuid = currentOrganization?.uuid ?? null;

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
            const [assignments, cityPage] = await Promise.all([
                administrationService.listRoleAssignments(organizationUuid),
                fetchCities(organizationUuid),
            ]);
            setMembers(assignments.items.map((assignment) => ({
                id: assignment.assignmentUuid,
                name: assignment.name,
                email: assignment.email,
                phone: null,
                role: assignment.role as OrganizationRole,
                status: assignment.active ? 'active' : 'inactive',
                cityNames: assignment.cities.map((city) => city.name),
            })));
            setCities(cityPage.items.map((city) => ({ uuid: city.id, name: city.name })));
        } catch (cause) {
            setError(cause instanceof ApiError ? cause.message : 'Impossible de charger les membres de l’équipe.');
        } finally {
            setLoading(false);
        }
    }, [organizationUuid]);

    useEffect(() => { void reload(); }, [reload]);

    const rows = useMemo(() => members.filter((member) => {
        const query = search.trim().toLocaleLowerCase('fr');
        return (!query || [member.name, member.email].some((value) => value.toLocaleLowerCase('fr').includes(query)))
            && (role === 'all' || member.role === role);
    }), [members, search, role]);

    /** Création d’administrateurs, seule mutation d’équipe accordée au PATRON. */
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
            if (result.warnings.email) push('warning', result.warnings.email);
            await reload();
        } catch (cause) {
            push('error', actionErrorMessage(cause));
            throw cause;
        }
    };

    return { members, cities, rows, loading, error, reload, search, setSearch, role, setRole, createMember };
}
