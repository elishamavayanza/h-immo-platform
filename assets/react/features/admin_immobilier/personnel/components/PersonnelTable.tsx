import { DataTable } from '../../../../components/Data/DataTable';
import type { DataTableColumn } from '../../../../hook-components/Data/DataTable';
import { PopoverMenu } from '../../../../components/UI/PopoverMenu';
import type { PopoverMenuItem } from '../../../../hook-components/UI/PopoverMenu';
import { canDo } from '../../../shared/permissions';
import type { OrganizationRole } from '../../../../../services/api/api.types';
import type { PersonnelRow } from '../types/personnel.types';

/**
 * Table des travailleurs : actions Modifier, Affecter.
 * Pas de suppression (aucune route DELETE côté backend).
 */
export function PersonnelTable({ rows, role, onEdit, onAssign }: { rows: PersonnelRow[]; role: OrganizationRole | null; onEdit: (row: PersonnelRow) => void; onAssign: (row: PersonnelRow) => void }) {
    const columns: DataTableColumn<PersonnelRow>[] = [
        { key: 'name', title: 'Membre du personnel', sortable: true, render: (row) => <div className="organization-property-name"><strong>{row.name}</strong><small>{row.email ?? row.phone}</small></div> },
        { key: 'role', title: 'Fonction', sortable: true, render: (row) => row.role },
        { key: 'city', title: 'Ville', sortable: true, render: (row) => row.city },
        { key: 'assignments', title: 'Affectations', sortable: true },
        { key: 'phone', title: 'Téléphone', sortable: true },
        {
            key: 'actions',
            title: 'Actions',
            render: (row) => {
                const items: PopoverMenuItem[] = [
                    ...(canDo(role, 'update_worker')
                        ? [{ id: 'edit', label: 'Modifier', icon: <span aria-hidden="true">✎</span>, onClick: () => onEdit(row) }]
                        : []),
                    ...(canDo(role, 'create_worker_assignment')
                        ? [{ id: 'assign', label: 'Affecter', icon: <span aria-hidden="true">📍</span>, onClick: () => onAssign(row) }]
                        : []),
                ];

                if (items.length === 0) return '—';

                return <PopoverMenu placement="bottom" offset={6} items={items} trigger={<span className="organization-row-actions" aria-label={`Actions pour ${row.name}`}><span aria-hidden="true">•••</span></span>} />;
            },
        },
    ];

    return <DataTable columns={columns} data={rows} pageSize={12} initialSortKey="name" />;
}
