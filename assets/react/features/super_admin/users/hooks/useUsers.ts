import { useCallback, useEffect, useMemo, useState } from 'react';

import { useToast } from '../../../../app/layout/MainLayout/contexts/ToastContext';
import { ApiError } from '../../../../../services/api/api.types';
import { usersService } from '../services/usersService';
import type {
    UserOrganizationFilter,
    UserRow,
    UserRoleFilter,
    UserStatus,
    UserSuspendResult,
} from '../types/user.types';

function actionErrorMessage(cause: unknown): string {
    return cause instanceof ApiError ? cause.message : 'Une erreur inattendue est survenue. Réessayez.';
}

export function useUsers() {
    const { push } = useToast();
    const [users, setUsers] = useState<UserRow[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [search, setSearch] = useState('');
    const [roleFilter, setRoleFilter] = useState<UserRoleFilter>('all');
    const [statusFilter, setStatusFilter] = useState<'all' | UserStatus>('all');
    const [organizationFilter, setOrganizationFilter] = useState<UserOrganizationFilter>('all');

    const reload = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const rows = await usersService.list({ page: 1, limit: 100 });
            setUsers(rows);
        } catch (cause) {
            setError(cause instanceof ApiError ? cause.message : 'Impossible de charger les utilisateurs.');
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => { void reload(); }, [reload]);

    /** Organisations présentes dans le jeu de données, pour le filtre (select). */
    const organizations = useMemo(() => {
        const map = new Map<string, string>();
        for (const user of users) {
            for (const membership of user.memberships) {
                map.set(membership.organizationId, membership.organizationName);
            }
        }
        return [...map.entries()]
            .map(([id, name]) => ({ id, name }))
            .sort((a, b) => a.name.localeCompare(b.name, 'fr'));
    }, [users]);

    const filteredUsers = useMemo(() => users.filter((user) => {
        const query = search.trim().toLocaleLowerCase('fr');
        const matchesSearch = !query || [user.name, user.email, ...user.memberships.map((membership) => membership.organizationName)]
            .some((value) => value.toLocaleLowerCase('fr').includes(query));
        const matchesRole = roleFilter === 'all' || user.role === roleFilter;
        const matchesStatus = statusFilter === 'all' || user.status === statusFilter;
        const matchesOrganization =
            organizationFilter === 'all'
            || (organizationFilter === 'platform'
                ? user.memberships.length === 0
                : user.memberships.some((membership) => membership.organizationId === organizationFilter));

        return matchesSearch && matchesRole && matchesStatus && matchesOrganization;
    }), [users, search, roleFilter, statusFilter, organizationFilter]);

    /**
     * Suspend un compte : le backend désactive le compte, archive l'événement
     * et notifie l'utilisateur par email (échec mailer → `warnings`).
     */
    const suspendUser = async (id: string, reason?: string): Promise<UserSuspendResult> => {
        try {
            const result = await usersService.suspend(id, reason ? { reason } : {});
            setUsers((current) => current.map((user) => user.id === id ? result.user : user));
            push('success', result.flushDescription ?? 'Le compte a été suspendu.');
            if (result.warnings.emails) {
                push('warning', result.warnings.emails);
            }
            return result;
        } catch (cause) {
            push('error', actionErrorMessage(cause));
            throw cause;
        }
    };

    return {
        users,
        filteredUsers,
        organizations,
        loading,
        error,
        reload,
        search,
        setSearch,
        roleFilter,
        setRoleFilter,
        statusFilter,
        setStatusFilter,
        organizationFilter,
        setOrganizationFilter,
        suspendUser,
    };
}
