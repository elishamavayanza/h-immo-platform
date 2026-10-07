import { useMemo, useState } from 'react';

import { INITIAL_USERS } from '../services/usersService';
import type { UserRow, UserStatus } from '../types/user.types';

export function useUsers() {
    const [users, setUsers] = useState(INITIAL_USERS);
    const [search, setSearch] = useState('');
    const [roleFilter, setRoleFilter] = useState('all');
    const [statusFilter, setStatusFilter] = useState<'all' | UserStatus>('all');
    const filteredUsers = useMemo(() => users.filter((user) => {
        const query = search.trim().toLocaleLowerCase('fr');
        const matchesSearch = !query || [user.name, user.email, user.organization].some((value) => value.toLocaleLowerCase('fr').includes(query));
        return matchesSearch && (roleFilter === 'all' || user.role === roleFilter) && (statusFilter === 'all' || user.status === statusFilter);
    }), [users, search, roleFilter, statusFilter]);

    const addUser = (user: Omit<UserRow, 'id' | 'lastActivity'>) => {
        setUsers((current) => [{ ...user, id: `local-${Date.now()}`, lastActivity: 'Invitation envoyée' }, ...current]);
    };

    return { users, filteredUsers, search, setSearch, roleFilter, setRoleFilter, statusFilter, setStatusFilter, addUser };
}
