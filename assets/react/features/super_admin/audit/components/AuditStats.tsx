import { Card } from '../../../../components/UI/Card';
import type { AuditEntry } from '../types/audit.types';

export function AuditStats({ entries }: { entries: AuditEntry[] }) {
    return <div className="sa-management-stats">
        <Card className="sa-management-stat" padding="medium"><span>Événements affichés</span><strong>{entries.length}</strong><small>Sur la période sélectionnée</small></Card>
        <Card className="sa-management-stat" padding="medium"><span>Actions réussies</span><strong>{entries.filter((entry) => entry.outcome === 'success').length}</strong><small>Enregistrées dans le journal</small></Card>
        <Card className="sa-management-stat" padding="medium"><span>Alertes</span><strong>{entries.filter((entry) => entry.outcome !== 'success').length}</strong><small>À examiner</small></Card>
        <Card className="sa-management-stat" padding="medium"><span>Acteurs distincts</span><strong>{new Set(entries.map((entry) => entry.actor)).size}</strong><small>Utilisateurs et système</small></Card>
    </div>;
}
