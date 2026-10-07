import { useMemo } from 'react';
import { DataTable } from '../../../../components/Data/DataTable';
import type { DataTableColumn } from '../../../../hook-components/Data/DataTable';
import { SearchInput } from '../../../../components/Forms/SearchInput';
import { Select } from '../../../../components/Forms/Select';
import { Badge } from '../../../../components/UI/Badge';
import { Card } from '../../../../components/UI/Card';
import { IconButton } from '../../../../components/UI/IconButton';
import { RightSidebar } from '../../../../components/Navigation/RightSidebar';
import { PlatformPageHeader } from '../../shared/PlatformPageHeader';
import { AuditEntryDetails } from '../components/AuditEntryDetails';
import { AuditStats } from '../components/AuditStats';
import { useAuditLog } from '../hooks/useAuditLog';
import type { AuditEntry } from '../types/audit.types';
import '../../../../../styles/pages/super_admin/audit/_audit.scss';

const ACTION_LABELS: Record<string, string> = {
    CREATE: 'Création', UPDATE: 'Modification', DELETE: 'Suppression', SUSPEND: 'Suspension',
    LOGIN_FAILED: 'Connexion refusée', LOGIN: 'Connexion', LOGOUT: 'Déconnexion',
};
const ACTION_ICON = <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 11v5M12 8h.01" /></svg>;

export function AuditLogPage() {
    const { entries, search, setSearch, action, setAction, selectedEntry, setSelectedEntry } = useAuditLog();
    const columns = useMemo<DataTableColumn<AuditEntry>[]>(() => [
        { key: 'createdAt', title: 'Date et heure', sortable: true, render: (entry) => new Date(entry.createdAt).toLocaleString('fr-FR') },
        { key: 'userId', title: 'Acteur', render: (entry) => entry.userId ? <span className="audit-uuid" title={entry.userId}>{entry.userId}</span> : <Badge variant="secondary">Système</Badge> },
        { key: 'action', title: 'Action', sortable: true, render: (entry) => <Badge variant="info">{ACTION_LABELS[entry.action] ?? entry.action}</Badge> },
        { key: 'entityType', title: 'Entité', sortable: true, render: (entry) => entry.entityType.split('\\').at(-1) ?? entry.entityType },
        { key: 'details', title: '', render: (entry) => <IconButton variant="ghost" ariaLabel={`Détails de l’événement ${entry.id}`} icon={ACTION_ICON} onClick={() => setSelectedEntry(entry)} /> },
    ], [setSelectedEntry]);

    return <div className="sa-audit-layout">
        <main className="sa-dashboard sa-management-page sa-audit-page">
            <PlatformPageHeader title="Journal d’audit" description="Consultez les actions enregistrées et les valeurs modifiées." icon="shield" />
            <AuditStats entries={entries} />
            <Card className="sa-management-table-card" padding="medium">
                <div className="sa-management-toolbar"><div><h2>Événements récents</h2><p>Maquette alignée sur AuditLogResponse ; les données sont encore locales.</p></div>
                    <div className="sa-management-filters sa-audit-filters">
                        <SearchInput value={search} onSearch={setSearch} placeholder="Rechercher une action ou entité…" fullWidth />
                        <Select aria-label="Filtrer par action" value={action} onChange={(event) => setAction(event.target.value)} options={[{ value: 'all', label: 'Toutes les actions' }, ...Object.entries(ACTION_LABELS).map(([value, label]) => ({ value, label }))]} />
                    </div>
                </div>
                <DataTable columns={columns} data={entries} pageSize={8} initialSortKey="createdAt" initialSortDirection="desc" />
            </Card>
        </main>
        <RightSidebar title="Détail de l’événement" size="small" variant="dark" collapsible>
            <AuditEntryDetails entry={selectedEntry} />
        </RightSidebar>
    </div>;
}
