import { InlineSummary } from '../../../shared/components/InlineSummary';
import type { AuditEntry } from '../types/audit.types';

export function AuditStats({ entries }: { entries: AuditEntry[] }) {
    return <InlineSummary items={[
        { label: 'Événements affichés', value: entries.length },
        { label: 'Actions plateforme', value: entries.filter((entry) => entry.organizationId === null).length },
        { label: 'Avec changements', value: entries.filter((entry) => entry.oldValues !== null || entry.newValues !== null).length },
        { label: 'Acteurs identifiés', value: new Set(entries.flatMap((entry) => entry.userId ? [entry.userId] : [])).size },
    ]} />;
}
