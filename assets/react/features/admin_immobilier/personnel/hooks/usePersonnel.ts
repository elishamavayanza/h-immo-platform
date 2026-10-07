import { useMemo, useState } from 'react';
import { PERSONNEL } from '../services/personnelService';
import { useAuth } from '../../../../app/providers/AuthProvider';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
export function usePersonnel() { const [search, setSearch] = useState(''); const [city, setCity] = useState('all'); const { user } = useAuth(); const { organizationRole } = useOrganization(); const cities = organizationRole === 'admin_ville' ? user?.cities.map((item) => item.name) ?? [] : null; const rows = useMemo(() => PERSONNEL.filter((row) => (cities === null || cities.includes(row.city)) && (() => { const q = search.trim().toLocaleLowerCase('fr'); return (!q || [row.name, row.role, row.city].some((v) => v.toLocaleLowerCase('fr').includes(q))) && (city === 'all' || row.city === city); })()), [search, city, cities]); return { rows, search, setSearch, city, setCity }; }
