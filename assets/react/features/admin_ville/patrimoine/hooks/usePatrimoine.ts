import { useMemo, useState } from 'react';

import { useAuth } from '../../../../app/providers/AuthProvider';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { PATRIMOINE } from '../services/patrimoineService';

export function usePatrimoine() {
    const [search, setSearch] = useState('');
    const [city, setCity] = useState('all');
    const [kind, setKind] = useState('all');
    const { user } = useAuth();
    const { organizationRole } = useOrganization();
    const assignedCities = organizationRole === 'admin_ville'
        ? user?.cities.map((item) => item.name) ?? []
        : null;

    const scopedRows = useMemo(
        () => PATRIMOINE.filter((item) => assignedCities === null || assignedCities.includes(item.city)),
        [assignedCities],
    );
    const availableCities = useMemo(
        () => [...new Set(scopedRows.map((item) => item.city))],
        [scopedRows],
    );
    const rows = useMemo(() => {
        const query = search.trim().toLocaleLowerCase('fr');
        return scopedRows.filter((item) =>
            (!query || [item.name, item.address, item.city].some((value) => value.toLocaleLowerCase('fr').includes(query)))
            && (city === 'all' || item.city === city)
            && (kind === 'all' || item.kind === kind),
        );
    }, [scopedRows, search, city, kind]);

    return { rows, availableCities, search, setSearch, city, setCity, kind, setKind };
}
