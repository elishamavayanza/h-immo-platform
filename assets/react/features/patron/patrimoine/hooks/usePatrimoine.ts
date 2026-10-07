import { useMemo, useState } from 'react';
import { PATRIMOINE } from '../services/patrimoineService';
import { useAuth } from '../../../../app/providers/AuthProvider';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';

export function usePatrimoine() {
    const [search, setSearch] = useState('');
    const [city, setCity] = useState('all');
    const [kind, setKind] = useState('all');
    const { user } = useAuth();
    const { organizationRole } = useOrganization();
    const assignedCities = organizationRole === 'admin_ville' ? user?.cities.map((item) => item.name) ?? [] : null;
    const rows = useMemo(() => PATRIMOINE.filter((item) => {
        const query = search.trim().toLocaleLowerCase('fr');
        return (assignedCities === null || assignedCities.includes(item.city)) && (!query || [item.name, item.address, item.city].some((value) => value.toLocaleLowerCase('fr').includes(query))) && (city === 'all' || item.city === city) && (kind === 'all' || item.kind === kind);
    }), [search, city, kind, assignedCities]);
    return { rows, search, setSearch, city, setCity, kind, setKind };
}
