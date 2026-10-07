import { useMemo, useState } from 'react';
import { LISTINGS } from '../services/vitrineService';
import { useAuth } from '../../../../app/providers/AuthProvider';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
export function useVitrine() { const [search, setSearch] = useState(''); const [status, setStatus] = useState('all'); const { user } = useAuth(); const { organizationRole } = useOrganization(); const cities = organizationRole === 'admin_ville' ? user?.cities.map((city) => city.name) ?? [] : null; const rows = useMemo(() => LISTINGS.filter((row) => (cities === null || cities.includes(row.city)) && (() => { const q = search.trim().toLocaleLowerCase('fr'); return (!q || [row.title, row.city, row.type].some((v) => v.toLocaleLowerCase('fr').includes(q))) && (status === 'all' || row.status === status); })()), [search, status, cities]); return { rows, search, setSearch, status, setStatus }; }
