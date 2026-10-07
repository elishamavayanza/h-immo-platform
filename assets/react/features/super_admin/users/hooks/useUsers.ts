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

    const addUser = (user: Omit<UserRow, 'id' | 'lastLoginAt'>) => {
        setUsers((current) => [{ ...user, id: crypto.randomUUID(), lastLoginAt: null }, ...current]);
    };

    const updateUser = (id: string, changes: Partial<Omit<UserRow, 'id' | 'lastLoginAt'>>) => setUsers((current) => current.map((user) => user.id === id ? { ...user, ...changes } : user));
    const deleteUser = (id: string) => setUsers((current) => current.filter((user) => user.id !== id));

    return { users, filteredUsers, search, setSearch, roleFilter, setRoleFilter, statusFilter, setStatusFilter, addUser, updateUser, deleteUser };
}
