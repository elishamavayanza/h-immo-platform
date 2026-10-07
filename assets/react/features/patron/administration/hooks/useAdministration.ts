import { useMemo, useState } from 'react';
import { TEAM } from '../services/administrationService';
export function useAdministration() { const [search, setSearch] = useState(''); const [role, setRole] = useState('all'); const rows = useMemo(() => TEAM.filter((member) => { const q = search.trim().toLocaleLowerCase('fr'); return (!q || [member.name, member.email, member.scope].some((v) => v.toLocaleLowerCase('fr').includes(q))) && (role === 'all' || member.role === role); }), [search, role]); return { rows, search, setSearch, role, setRole }; }
