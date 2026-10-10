import { DataTable } from '../../../../components/Data/DataTable';
import type { DataTableColumn } from '../../../../hook-components/Data/DataTable';
import { Badge } from '../../../../components/UI/Badge';
import { PopoverMenu } from '../../../../components/UI/PopoverMenu';
import type { PopoverMenuItem } from '../../../../hook-components/UI/PopoverMenu';
import { formatUserDate } from '../../../../services/userPreferences';
import { canDo } from '../../../shared/permissions';
import type { OrganizationRole } from '../../../../../services/api/api.types';
import type { LocataireRow, TenantKind } from '../types/locataire.types';

const TYPE_LABEL: Record<TenantKind, string> = {
    individual: 'Personne physique',
    company: 'Personne morale',
};
const TYPE_VARIANT: Record<TenantKind, 'secondary' | 'info'> = {
    individual: 'secondary',
    company: 'info',
};

interface LocatairesTableProps {
    rows: LocataireRow[];
    role: OrganizationRole | null;
    onEdit: (row: LocataireRow) => void;
    onArchive: (row: LocataireRow) => void;
    onNewLease: (row: LocataireRow) => void;
}

/**
 * Table des locataires : actions Modifier, Archiver, Nouveau bail.
 * Archiver = suppression logique (`ARCHIVE_TENANT`). Pas de suppression définitive.
 */
export function LocatairesTable({ rows, role, onEdit, onArchive, onNewLease }: LocatairesTableProps) {
    const columns: DataTableColumn<LocataireRow>[] = [
        {
            key: 'name',
            title: 'Locataire',
            sortable: true,
            render: (row) => <div className="organization-property-name"><strong>{row.name}</strong><small>{row.sublabel}</small></div>,
        },
        { key: 'type', title: 'Type', sortable: true, render: (row) => <Badge variant={TYPE_VARIANT[row.type]}>{TYPE_LABEL[row.type]}</Badge> },
        { key: 'unitReference', title: 'Unité', sortable: true, render: (row) => row.unitReference ?? '—' },
        { key: 'address', title: 'Adresse', sortable: true },
        { key: 'leaseEnd', title: 'Fin du bail', sortable: true, render: (row) => (row.leaseEnd ? formatUserDate(row.leaseEnd) : 'Aucun bail actif') },
        {
            key: 'actions',
            title: 'Actions',
            render: (row) => {
                const items: PopoverMenuItem[] = [
                    ...(canDo(role, 'update_tenant')
                        ? [{ id: 'edit', label: 'Modifier', icon: <span aria-hidden="true">✎</span>, onClick: () => onEdit(row) }]
                        : []),
                    ...(canDo(role, 'create_lease')
                        ? [{ id: 'new-lease', label: 'Nouveau bail', icon: <span aria-hidden="true">📄</span>, onClick: () => onNewLease(row) }]
                        : []),
                    ...(canDo(role, 'archive_tenant')
                        ? [{ id: 'archive', label: 'Archiver', icon: <span aria-hidden="true">🗄</span>, danger: true, onClick: () => onArchive(row) }]
                        : []),
                ];

                if (items.length === 0) return '—';

                return <PopoverMenu placement="bottom" offset={6} items={items} trigger={<span className="organization-row-actions" aria-label={`Actions pour ${row.name}`}><span aria-hidden="true">•••</span></span>} />;
            },
        },
    ];

    return <DataTable columns={columns} data={rows} pageSize={12} initialSortKey="name" />;
}
