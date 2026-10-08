import { InlineSummary } from '../../../shared/components/InlineSummary';
import type { AuditEntry } from '../types/audit.types';

export function AuditStats({ entries, total, page, pages }: { entries: AuditEntry[]; total: number; page: number; pages: number }) {
    return (
        <InlineSummary items={[
            { label: 'Entrées trouvées', value: total },
            { label: 'Sur cette page', value: entries.length },
            { label: 'Actions plateforme', value: entries.filter((entry) => entry.organizationId === null).length, detail: '(page)' },
            { label: 'Avec changements', value: entries.filter((entry) => entry.oldValues !== null || entry.newValues !== null).length, detail: '(page)' },
            { label: 'Acteurs identifiés', value: new Set(entries.flatMap((entry) => entry.userId ? [entry.userId] : [])).size, detail: '(page)' },
            { label: 'Pagination', value: `${page} / ${pages}` },
        ]} />
    );
}