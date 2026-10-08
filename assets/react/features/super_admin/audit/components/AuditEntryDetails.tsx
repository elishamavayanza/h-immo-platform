import { Badge } from '../../../../components/UI/Badge';
import { Card } from '../../../../components/UI/Card';
import type { AuditEntry } from '../types/audit.types';

const FIELD_LABELS: Record<string, string> = {
    name: 'Nom',
    status: 'Statut',
    role: 'Rôle',
    email: 'Adresse e-mail',
    rate: 'Taux',
    isActive: 'Compte actif',
    reason: 'Motif',
};

function formatValue(value: unknown): string {
    if (typeof value === 'boolean') return value ? 'Oui' : 'Non';
    if (value === null) return '—';
    if (typeof value === 'object') return JSON.stringify(value, null, 2);
    return String(value);
}

function ValuesBlock({ title, values }: { title: string; values: Record<string, unknown> | null }) {
    return (
        <Card className="audit-detail__values" variant="outlined" padding="small">
            <h3>{title}</h3>
            {values && Object.keys(values).length > 0 ? (
                <dl className="audit-detail__changes">
                    {Object.entries(values).map(([key, value]) => (
                        <div key={key}>
                            <dt>{FIELD_LABELS[key] ?? key}</dt>
                            <dd>{formatValue(value)}</dd>
                        </div>
                    ))}
                </dl>
            ) : (
                <p>Aucune valeur enregistrée.</p>
            )}
        </Card>
    );
}

export function AuditEntryDetails({ entry, onClose }: { entry: AuditEntry | null; onClose?: () => void }) {
    if (!entry) return <p className="audit-detail__empty">Sélectionnez un événement pour afficher son détail.</p>;

    const date = new Intl.DateTimeFormat('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hourCycle: 'h23',
        timeZone: 'Africa/Kinshasa',
    }).format(new Date(entry.createdAt));
    const actionVariant = entry.action === 'CREATE' || entry.action === 'LOGIN'
        ? 'success'
        : entry.action === 'DELETE' || entry.action === 'LOGIN_FAILED'
            ? 'error'
            : entry.action === 'SUSPEND'
                ? 'warning'
                : 'info';

    return (
        <div className="audit-detail">
            <div className="audit-detail__header">
                <div className="audit-detail__summary">
                    <Badge variant={actionVariant} size="small">
                        {entry.action.replace(/_/g, ' ')}
                    </Badge>
                    <time dateTime={entry.createdAt}>{date}</time>
                </div>
                {onClose && (
                    <button type="button" className="audit-detail__close" onClick={onClose} aria-label="Fermer le détail">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                    </button>
                )}
            </div>

            <dl className="audit-detail__metadata">
                <div><dt>Entité</dt><dd title={entry.entityType}>{entry.entityType.split('\\').at(-1) ?? entry.entityType}</dd></div>
                <div><dt>Utilisateur</dt><dd>{entry.userId ?? 'Action système'}</dd></div>
                <div><dt>Organisation</dt><dd>{entry.organizationId ?? 'Plateforme'}</dd></div>
            </dl>

            <div className="audit-detail__changes-group" aria-label="Valeurs modifiées">
                <ValuesBlock title="Avant" values={entry.oldValues} />
                <ValuesBlock title="Après" values={entry.newValues} />
            </div>
        </div>
    );
}