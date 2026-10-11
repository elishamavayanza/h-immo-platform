import { DataTable } from '../../../../components/Data/DataTable';
import type { DataTableColumn } from '../../../../hook-components/Data/DataTable';
import { Badge } from '../../../../components/UI/Badge';
import { PopoverMenu } from '../../../../components/UI/PopoverMenu';
import type { PopoverMenuItem } from '../../../../hook-components/UI/PopoverMenu';
import { Icon } from '../../../../components/UI/Icon/Icon';
import { formatUserDate } from '../../../../services/userPreferences';
import { formatMoney } from '../../../../../utils/format.utils';
import { canDo } from '../../../shared/permissions';
import type { OrganizationRole } from '../../../../../services/api/api.types';
import type { DepenseRow } from '../types/depense.types';

interface DepensesTableProps {
    rows: DepenseRow[];
    role: OrganizationRole | null;
    onEdit: (row: DepenseRow) => void;
    onCancel: (row: DepenseRow) => void;
    /** Lecture seule (espace PATRON) : masque toute action d'écriture. */
    readOnly?: boolean;
}

/**
 * Table des dépenses : actions Modifier, Annuler (contre-écriture).
 * Pas de suppression définitive : l'historique comptable doit rester consultable.
 * `readOnly` masque la colonne d'actions (le Patron consulte sans opérer).
 */
export function DepensesTable({ rows, role, onEdit, onCancel, readOnly = false }: DepensesTableProps) {
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
                if (readOnly) return '—';

                const items: PopoverMenuItem[] = [
                    ...(canDo(role, 'update_expense')
                        ? [{ id: 'edit', label: 'Modifier', icon: <Icon name="edit" />, onClick: () => onEdit(row) }]
                        : []),
                    ...(canDo(role, 'delete_expense')
                        ? [{ id: 'cancel', label: 'Annuler', icon: <span aria-hidden="true">↩</span>, danger: true, onClick: () => onCancel(row) }]
                        : []),
                ];

                if (items.length === 0) return '—';

                return <PopoverMenu placement="bottom" offset={6} items={items} trigger={<span className="organization-row-actions" aria-label={`Actions pour ${row.description}`}><Icon name="more" /></span>} />;
            },
        },
    ];

    return <DataTable columns={columns} data={rows} pageSize={12} initialSortKey="date" initialSortDirection="desc" />;
}
