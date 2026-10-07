import { useMemo, useState } from 'react';

import { useAuth } from '../../../../app/providers/AuthProvider';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { PERSONNEL } from '../services/personnelService';

export function usePersonnel() {
    const [search, setSearch] = useState('');
    const [city, setCity] = useState('all');
    const { user } = useAuth();
    const { organizationRole } = useOrganization();
    const assignedCities = organizationRole === 'admin_ville'
        ? user?.cities.map((item) => item.name) ?? []
        : null;
    const scopedRows = useMemo(
        () => PERSONNEL.filter((row) => assignedCities === null || assignedCities.includes(row.city)),
        [assignedCities],
    );
    const availableCities = useMemo(
        () => [...new Set(scopedRows.map((row) => row.city))],
        [scopedRows],
    );
    const rows = useMemo(() => {
        const query = search.trim().toLocaleLowerCase('fr');
        return scopedRows.filter((row) =>
            (!query || [row.name, row.role, row.city].some((value) => value.toLocaleLowerCase('fr').includes(query)))
            && (city === 'all' || row.city === city),
        );
    }, [scopedRows, search, city]);

    return { rows, availableCities, search, setSearch, city, setCity };
}
