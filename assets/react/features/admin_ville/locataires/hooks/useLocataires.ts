import { useMemo, useState } from 'react';
import { LOCATAIRES } from '../services/locatairesService';
import { useAuth } from '../../../../app/providers/AuthProvider';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';

export function useLocataires() {
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('all');
    const { user } = useAuth();
    const { organizationRole } = useOrganization();
    const assignedCities = organizationRole === 'admin_ville' ? user?.cities.map((item) => item.name) ?? [] : null;
    const rows = useMemo(() => LOCATAIRES.filter((tenant) => {
        const query = search.trim().toLocaleLowerCase('fr');
        return (assignedCities === null || assignedCities.includes(tenant.city)) && (!query || [tenant.name, tenant.email, tenant.property].some((value) => value.toLocaleLowerCase('fr').includes(query))) && (status === 'all' || tenant.status === status);
    }), [search, status, assignedCities]);
    return { rows, search, setSearch, status, setStatus };
}
