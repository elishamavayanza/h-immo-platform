import { useMemo, useState } from 'react';

import { INITIAL_AUDIT_ENTRIES } from '../services/auditService';
import type { AuditEntry } from '../types/audit.types';

export function useAuditLog() {
    const [search, setSearch] = useState('');
    const [action, setAction] = useState('all');
    const [selectedEntry, setSelectedEntry] = useState<AuditEntry | null>(INITIAL_AUDIT_ENTRIES[0] ?? null);
    const entries = useMemo(() => INITIAL_AUDIT_ENTRIES.filter((entry) => {
        const term = search.trim().toLocaleLowerCase('fr');
        const matchesSearch = !term || [entry.userId ?? 'Système', entry.action, entry.entityType, entry.organizationId ?? 'Plateforme'].some((value) => value.toLocaleLowerCase('fr').includes(term));
        return matchesSearch && (action === 'all' || entry.action === action);
    }), [search, action]);
    return { entries, search, setSearch, action, setAction, selectedEntry, setSelectedEntry };
}
