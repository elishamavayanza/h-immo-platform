import { Card } from '../../../../components/UI/Card';
import type { AuditEntry } from '../types/audit.types';

export function AuditStats({ entries }: { entries: AuditEntry[] }) {
    return <div className="sa-management-stats">
        <Card className="sa-management-stat" padding="medium"><span>Événements affichés</span><strong>{entries.length}</strong><small>Selon les filtres appliqués</small></Card>
        <Card className="sa-management-stat" padding="medium"><span>Actions plateforme</span><strong>{entries.filter((entry) => entry.organizationId === null).length}</strong><small>Sans organisation rattachée</small></Card>
        <Card className="sa-management-stat" padding="medium"><span>Avec changements</span><strong>{entries.filter((entry) => entry.oldValues !== null || entry.newValues !== null).length}</strong><small>Valeurs avant ou après disponibles</small></Card>
        <Card className="sa-management-stat" padding="medium"><span>Acteurs identifiés</span><strong>{new Set(entries.flatMap((entry) => entry.userId ? [entry.userId] : [])).size}</strong><small>UUID présents dans le journal</small></Card>
    </div>;
}
