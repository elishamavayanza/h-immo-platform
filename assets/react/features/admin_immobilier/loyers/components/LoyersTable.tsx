import { DataTable } from '../../../../components/Data/DataTable';
import type { DataTableColumn } from '../../../../hook-components/Data/DataTable';
import { Badge } from '../../../../components/UI/Badge';
import { formatUserDate } from '../../../../services/userPreferences';
import { formatMoney } from '../../../../../utils/format.utils';
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

/**
 * Table des échéances : lecture seule.
 *
 * Pas d'action « Marquer en retard » : `overdue` est un état dérivé du
 * montant payé et de la date d'échéance, pas une valeur modifiable à la main.
 */
export function LoyersTable({ rows }: { rows: LoyerRow[] }) {
    const columns: DataTableColumn<LoyerRow>[] = [
        { key: 'tenant', title: 'Locataire', sortable: true },
        { key: 'unitLabel', title: 'Bien / unité', sortable: true, render: (row) => row.unitLabel },
        { key: 'periodLabel', title: 'Période', sortable: true },
        { key: 'dueDate', title: 'Échéance', sortable: true, render: (row) => formatUserDate(row.dueDate) },
        { key: 'amount', title: 'Montant dû', sortable: true, render: (row) => formatMoney(row.amount, row.currency) },
        { key: 'status', title: 'Statut', sortable: true, render: (row) => <Badge variant={STATUS_VARIANT[row.status]}>{STATUS_LABEL[row.status]}</Badge> },
    ];

    return <DataTable columns={columns} data={rows} pageSize={12} initialSortKey="dueDate" initialSortDirection="desc" />;
}
