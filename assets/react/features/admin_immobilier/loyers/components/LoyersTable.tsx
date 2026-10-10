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
import type { LoyerRow, RentStatusCode } from '../types/loyer.types';

const STATUS_LABEL: Record<RentStatusCode, string> = {
    pending: 'En attente',
    partially_paid: 'Partiel',
    paid: 'Payé',
    overdue: 'En retard',
};
const STATUS_VARIANT: Record<RentStatusCode, 'warning' | 'info' | 'success' | 'error'> = {
    pending: 'warning',
    partially_paid: 'info',
    paid: 'success',
    overdue: 'error',
};

interface LoyersTableProps {
    rows: LoyerRow[];
    role: OrganizationRole | null;
    onRecordPayment: (row: LoyerRow) => void;
    onMarkOverdue: (row: LoyerRow) => void;
    onUpdate: (row: LoyerRow) => void;
}

/**
 * Table des échéances : actions Enregistrer un paiement, Marquer en retard, Modifier.
 * « Enregistrer un paiement » masqué si la ligne est PAID.
 * « Marquer en retard » masqué si déjà OVERDUE ou PAID.
 */
export function LoyersTable({ rows, role, onRecordPayment, onMarkOverdue, onUpdate }: LoyersTableProps) {
    const columns: DataTableColumn<LoyerRow>[] = [
        { key: 'tenant', title: 'Locataire', sortable: true },
        { key: 'unitLabel', title: 'Bien / unité', sortable: true, render: (row) => row.unitLabel },
        { key: 'periodLabel', title: 'Période', sortable: true },
        { key: 'dueDate', title: 'Échéance', sortable: true, render: (row) => formatUserDate(row.dueDate) },
        { key: 'amount', title: 'Montant dû', sortable: true, render: (row) => formatMoney(row.amount, row.currency) },
        { key: 'status', title: 'Statut', sortable: true, render: (row) => <Badge variant={STATUS_VARIANT[row.status]}>{STATUS_LABEL[row.status]}</Badge> },
        {
            key: 'actions',
            title: 'Actions',
            render: (row) => {
                const isPaid = row.status === 'paid';
                const isOverdue = row.status === 'overdue';
                const items: PopoverMenuItem[] = [
                    ...(!isPaid && canDo(role, 'create_payment')
                        ? [{ id: 'payment', label: 'Enregistrer un paiement', icon: <Icon name="money" />, onClick: () => onRecordPayment(row) }]
                        : []),
                    ...(!isOverdue && !isPaid && canDo(role, 'mark_rent_overdue')
                        ? [{ id: 'overdue', label: 'Marquer en retard', icon: <Icon name="alert" />, onClick: () => onMarkOverdue(row) }]
                        : []),
                    ...(canDo(role, 'update_rent')
                        ? [{ id: 'edit', label: 'Modifier', icon: <Icon name="edit" />, onClick: () => onUpdate(row) }]
                        : []),
                ];

                if (items.length === 0) return '—';

                return <PopoverMenu placement="bottom" offset={6} items={items} trigger={<span className="organization-row-actions" aria-label={`Actions pour ${row.tenant}`}><Icon name="more" /></span>} />;
            },
        },
    ];

    return <DataTable columns={columns} data={rows} pageSize={12} initialSortKey="dueDate" initialSortDirection="desc" />;
}
