import { useMemo } from 'react';
import { DataTable } from '../../../../components/Data/DataTable';
import type { DataTableColumn } from '../../../../hook-components/Data/DataTable';
import { Select } from '../../../../components/Forms/Select';
import { Badge } from '../../../../components/UI/Badge';
import { Card } from '../../../../components/UI/Card';
import { IconButton } from '../../../../components/UI/IconButton';
import { RightSidebar } from '../../../../components/Navigation/RightSidebar';
import { PlatformPageHeader } from '../../shared/PlatformPageHeader';
import { AuditEntryDetails } from '../components/AuditEntryDetails';
import { AuditStats } from '../components/AuditStats';
import { useAuditLog } from '../hooks/useAuditLog';
import { formatUserDate } from '../../../../services/userPreferences';
import type { AuditEntry } from '../types/audit.types';
import '../../../../../styles/pages/super_admin/audit/_audit.scss';

const ACTION_ICON = (
    <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" strokeWidth={2} aria-hidden="true">
        <circle cx="12" cy="12" r="9" />
        <path d="M12 11v5M12 8h.01" />
    </svg>
);

const ENTITY_LABELS: Record<string, string> = {
    'App\\Entity\\Identity\\Organization': 'Organisation',
    'App\\Entity\\Identity\\OrganizationUser': 'Membre organisation',
    'App\\Entity\\Identity\\User': 'Utilisateur',
    'App\\Entity\\Identity\\UserCity': 'Ville utilisateur',
    'App\\Entity\\Identity\\PasswordResetToken': 'Jeton réinitialisation',
    'App\\Entity\\Identity\\RevokedToken': 'Jeton révoqué',
    'App\\Entity\\Property\\City': 'Ville',
    'App\\Entity\\Property\\Parcel': 'Parcelle',
    'App\\Entity\\Property\\Building': 'Bâtiment',
    'App\\Entity\\Property\\Unit': 'Unité',
    'App\\Entity\\Property\\UnitPhoto': 'Photo unité',
    'App\\Entity\\Rental\\Tenant': 'Locataire',
    'App\\Entity\\Rental\\Lease': 'Bail',
    'App\\Entity\\Rental\\Rent': 'Loyer',
    'App\\Entity\\Rental\\Payment': 'Paiement',
    'App\\Entity\\Expense\\Expense': 'Dépense',
    'App\\Entity\\Staff\\Worker': 'Personnel',
    'App\\Entity\\Staff\\WorkerAssignment': 'Affectation personnel',
    'App\\Entity\\System\\AuditLog': 'Journal d\'audit',
    'App\\Entity\\System\\ExchangeRate': 'Taux de change',
};

export function AuditLogPage() {
    const {
        entries,
        total,
        page,
        pages,
        loading,
        error,
        filters,
        selectedEntry,
        actionOptions,
        orgOptions,
        orgLoading,
        reload,
        handleFilterChange,
        handlePageChange,
        handleSort,
        selectEntry,
        clearSelection,
        organizationOptions,
    } = useAuditLog();

    const columns = useMemo<DataTableColumn<AuditEntry>[]>(() => [
        { key: 'createdAt', title: 'Date et heure', render: (entry) => formatUserDate(entry.createdAt, true) },
        {
            key: 'organizationId',
            title: 'Organisation',
            render: (entry) => {
                if (!entry.organizationId) return <Badge variant="secondary">Plateforme</Badge>;
                const org = organizationOptions.find((o) => o.id === entry.organizationId);
                return org ? <span title={entry.organizationId}>{org.name}</span> : <span className="audit-uuid" title={entry.organizationId}>{entry.organizationId.slice(0, 8)}…</span>;
            },
        },
        {
            key: 'userId',
            title: 'Acteur',
            render: (entry) => entry.userId ? <span className="audit-uuid" title={entry.userId}>{entry.userId.slice(0, 8)}…</span> : <Badge variant="secondary">Système</Badge>,
        },
        { key: 'action', title: 'Action', render: (entry) => <Badge variant="info">{entry.action.replace(/_/g, ' ')}</Badge> },
        { key: 'entityType', title: 'Entité', render: (entry) => ENTITY_LABELS[entry.entityType] ?? entry.entityType.split('\\').at(-1) ?? entry.entityType },
        { key: 'details', title: '', render: (entry) => <IconButton variant="ghost" ariaLabel={`Détails de l'événement ${entry.id}`} icon={ACTION_ICON} onClick={() => selectEntry(entry)} /> },
    ], [selectEntry, organizationOptions]);

    return (
        <div className="sa-audit-layout">
            <main className="sa-dashboard sa-management-page sa-audit-page">
                <PlatformPageHeader title="Journal d'audit" description="Consultez les actions enregistrées et les valeurs modifiées." icon="shield" />
                <AuditStats entries={entries} total={total} page={page} pages={pages} />
                <Card className="sa-management-table-card" padding="medium">
                    <div className="sa-management-toolbar">
                        <div>
                            <h2>Événements récents</h2>
                            <p>{total} entrée{total > 1 ? 's' : ''} trouvée{total > 1 ? 's' : ''} · Page {page} / {pages}</p>
                        </div>
                        <div className="sa-management-filters sa-audit-filters">
                            <Select
                                aria-label="Filtrer par action"
                                value={filters.action ?? ''}
                                onChange={(event) => handleFilterChange('action', event.target.value || undefined)}
                                options={actionOptions}
                                fullWidth
                            />
                            <Select
                                aria-label="Filtrer par organisation"
                                value={filters.organizationUuid ?? ''}
                                onChange={(event) => handleFilterChange('organizationUuid', event.target.value || undefined)}
                                options={orgOptions}
                                disabled={orgLoading}
                                fullWidth
                            />
                        </div>
                    </div>
                    {error && (
                        <div className="sa-audit-error" role="alert">
                            <span>Erreur de chargement : {error}</span>
                            <button type="button" className="button button--ghost button--small" onClick={reload}>Réessayer</button>
                        </div>
                    )}
                    <DataTable
                        mode="server"
                        columns={columns}
                        data={entries}
                        pageSize={filters.itemsPerPage ?? 20}
                        initialSortKey="createdAt"
                        initialSortDirection="desc"
                        loading={loading}
                        totalItems={total}
                        currentPage={page}
                        onPageChange={handlePageChange}
                        onSort={handleSort}
                    />
                </Card>
            </main>
            <RightSidebar title="Détail de l'événement" size="medium" variant="dark" collapsible>
                <AuditEntryDetails entry={selectedEntry} onClose={clearSelection} />
            </RightSidebar>
        </div>
    );
}
