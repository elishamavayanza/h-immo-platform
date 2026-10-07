import { useMemo, useState } from 'react';

import { useAuth } from '../../../../app/providers/AuthProvider';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { DEPENSES } from '../services/depensesService';

export function useDepenses() {
    const [search, setSearch] = useState('');
    const [category, setCategory] = useState('all');
    const { user } = useAuth();
    const { organizationRole } = useOrganization();
    const assignedCities = organizationRole === 'admin_ville'
        ? user?.cities.map((item) => item.name) ?? []
        : null;
    const scopedRows = useMemo(
        () => DEPENSES.filter((row) => assignedCities === null || assignedCities.includes(row.city)),
        [assignedCities],
    );
    const availableCategories = useMemo(
        () => [...new Set(scopedRows.map((row) => row.category))],
        [scopedRows],
    );
    const rows = useMemo(() => {
        const query = search.trim().toLocaleLowerCase('fr');
        return scopedRows.filter((row) =>
            (!query || [row.category, row.property, row.description].some((value) => value.toLocaleLowerCase('fr').includes(query)))
            && (category === 'all' || row.category === category),
        );
    }, [scopedRows, search, category]);

    return { rows, availableCategories, search, setSearch, category, setCategory };
}
