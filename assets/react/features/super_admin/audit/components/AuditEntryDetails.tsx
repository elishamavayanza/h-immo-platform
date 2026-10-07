import { Badge } from '../../../../components/UI/Badge';
import type { AuditEntry } from '../types/audit.types';

function ValuesBlock({ title, values }: { title: string; values: Record<string, unknown> | null }) {
    return <section className="audit-detail__values"><h3>{title}</h3>{values ? <pre>{JSON.stringify(values, null, 2)}</pre> : <p>Aucune valeur enregistrée.</p>}</section>;
}

export function AuditEntryDetails({ entry }: { entry: AuditEntry | null }) {
    if (!entry) return <p className="audit-detail__empty">Sélectionnez un événement pour afficher son détail.</p>;
    return <div className="audit-detail">
        <div className="audit-detail__summary"><Badge variant="info">{entry.action}</Badge><time dateTime={entry.createdAt}>{new Date(entry.createdAt).toLocaleString('fr-FR')}</time></div>
        <dl className="audit-detail__metadata">
            <div><dt>Entité</dt><dd>{entry.entityType}</dd></div>
            <div><dt>Utilisateur</dt><dd>{entry.userId ?? 'Action système'}</dd></div>
            <div><dt>Organisation</dt><dd>{entry.organizationId ?? 'Plateforme'}</dd></div>
        </dl>
        <ValuesBlock title="Avant" values={entry.oldValues} />
        <ValuesBlock title="Après" values={entry.newValues} />
    </div>;
}
