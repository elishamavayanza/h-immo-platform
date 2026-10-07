import { useMemo, useState } from 'react';
import { LOYERS } from '../services/loyersService';
import { useAuth } from '../../../../app/providers/AuthProvider';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
export function useLoyers() { const [search, setSearch] = useState(''); const [status, setStatus] = useState('all'); const { user } = useAuth(); const { organizationRole } = useOrganization(); const cities = organizationRole === 'admin_ville' ? user?.cities.map((city) => city.name) ?? [] : null; const rows = useMemo(() => LOYERS.filter((row) => (cities === null || cities.includes(row.city)) && (() => { const q = search.trim().toLocaleLowerCase('fr'); return (!q || [row.tenant, row.property, row.period].some((v) => v.toLocaleLowerCase('fr').includes(q))) && (status === 'all' || row.status === status); })()), [search, status, cities]); return { rows, search, setSearch, status, setStatus }; }
