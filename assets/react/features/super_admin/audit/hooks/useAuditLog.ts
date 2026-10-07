import { useMemo, useState } from 'react';

import { INITIAL_AUDIT_ENTRIES } from '../services/auditService';
import type { AuditCategory } from '../types/audit.types';

export function useAuditLog() {
    const [search, setSearch] = useState('');
    const [category, setCategory] = useState<'all' | AuditCategory>('all');
    const [outcome, setOutcome] = useState('all');
    const entries = useMemo(() => INITIAL_AUDIT_ENTRIES.filter((entry) => {
        const term = search.trim().toLocaleLowerCase('fr');
        const matchesSearch = !term || [entry.actor, entry.action, entry.target, entry.ipAddress].some((value) => value.toLocaleLowerCase('fr').includes(term));
        return matchesSearch && (category === 'all' || entry.category === category) && (outcome === 'all' || entry.outcome === outcome);
    }), [search, category, outcome]);
    return { entries, search, setSearch, category, setCategory, outcome, setOutcome };
}
