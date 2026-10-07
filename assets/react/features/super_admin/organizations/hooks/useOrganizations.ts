import { useMemo, useState } from 'react';

import { INITIAL_ORGANIZATIONS } from '../services/organizationsService';
import type { OrganizationRow, OrganizationStatusFilter } from '../types/organization.types';

export function useOrganizations() {
    const [organizations, setOrganizations] = useState(INITIAL_ORGANIZATIONS);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState<OrganizationStatusFilter>('all');
    const filteredOrganizations = useMemo(() => organizations.filter((organization) => {
        const query = search.trim().toLocaleLowerCase('fr');
        const matchesSearch = !query || [organization.name, organization.code, organization.city].some((value) => value.toLocaleLowerCase('fr').includes(query));
        return matchesSearch && (statusFilter === 'all' || organization.status === statusFilter);
    }), [organizations, search, statusFilter]);

    const addOrganization = (organization: Omit<OrganizationRow, 'id' | 'createdAt'>) => {
        setOrganizations((current) => [{ ...organization, id: crypto.randomUUID(), createdAt: new Date().toISOString() }, ...current]);
    };

    const updateOrganization = (id: string, changes: Partial<Omit<OrganizationRow, 'id' | 'createdAt'>>) => {
        setOrganizations((current) => current.map((organization) => organization.id === id ? { ...organization, ...changes } : organization));
    };

    const deleteOrganization = (id: string) => setOrganizations((current) => current.filter((organization) => organization.id !== id));

    return { organizations, filteredOrganizations, search, setSearch, statusFilter, setStatusFilter, addOrganization, updateOrganization, deleteOrganization };
}
