import { DataTable } from '../../../../components/Data/DataTable';
import type { DataTableColumn } from '../../../../hook-components/Data/DataTable';
import { Badge } from '../../../../components/UI/Badge';
import { PopoverMenu } from '../../../../components/UI/PopoverMenu';
import type { PopoverMenuItem } from '../../../../hook-components/UI/PopoverMenu';
import { formatUserDate } from '../../../../services/userPreferences';
import { formatMoney } from '../../../../../utils/format.utils';
import { canDo } from '../../../shared/permissions';
import type { OrganizationRole } from '../../../../../services/api/api.types';
import type { DepenseRow } from '../types/depense.types';

/**
 * Table des dépenses : actions Modifier, Annuler (contre-écriture).
 * Pas de suppression définitive : l'historique comptable doit rester consultable.
 */
export function DepensesTable({ rows, role, onEdit, onCancel }: { rows: DepenseRow[]; role: OrganizationRole | null; onEdit: (row: DepenseRow) => void; onCancel: (row: DepenseRow) => void }) {
    const columns: DataTableColumn<DepenseRow>[] = [
        { key: 'date', title: 'Date', sortable: true, render: (row) => formatUserDate(row.date) },
        { key: 'category', title: 'Catégorie', sortable: true },
        { key: 'property', title: 'Bien concerné', sortable: true, render: (row) => row.property },
        { key: 'description', title: 'Description', sortable: true },
        { key: 'city', title: 'Ville', sortable: true },
        { key: 'amount', title: 'Montant', sortable: true, render: (row) => formatMoney(row.amount, row.currency) },
        {
            key: 'actions',
            title: 'Actions',
            render: (row) => {
                const items: PopoverMenuItem[] = [
                    ...(canDo(role, 'update_expense')
                        ? [{ id: 'edit', label: 'Modifier', icon: <span aria-hidden="true">✎</span>, onClick: () => onEdit(row) }]
                        : []),
                    ...(canDo(role, 'delete_expense')
                        ? [{ id: 'cancel', label: 'Annuler', icon: <span aria-hidden="true">↩</span>, danger: true, onClick: () => onCancel(row) }]
                        : []),
                ];

                if (items.length === 0) return '—';

                return <PopoverMenu placement="bottom" offset={6} items={items} trigger={<span className="organization-row-actions" aria-label={`Actions pour ${row.description}`}><span aria-hidden="true">•••</span></span>} />;
            },
        },
    ];

    return <DataTable columns={columns} data={rows} pageSize={12} initialSortKey="date" initialSortDirection="desc" />;
}
