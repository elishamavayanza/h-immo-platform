import { useMemo, useState } from 'react';
import { DEPENSES } from '../services/depensesService';
import { useAuth } from '../../../../app/providers/AuthProvider';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
export function useDepenses() { const [search, setSearch] = useState(''); const [category, setCategory] = useState('all'); const { user } = useAuth(); const { organizationRole } = useOrganization(); const cities = organizationRole === 'admin_ville' ? user?.cities.map((city) => city.name) ?? [] : null; const rows = useMemo(() => DEPENSES.filter((row) => (cities === null || cities.includes(row.city)) && (() => { const q = search.trim().toLocaleLowerCase('fr'); return (!q || [row.category, row.property, row.description].some((v) => v.toLocaleLowerCase('fr').includes(q))) && (category === 'all' || row.category === category); })()), [search, category, cities]); return { rows, search, setSearch, category, setCategory }; }
