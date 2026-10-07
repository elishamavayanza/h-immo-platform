import { DataTable } from '../../../../components/Data/DataTable';
import type { DataTableColumn } from '../../../../hook-components/Data/DataTable';
import { Badge } from '../../../../components/UI/Badge';
import type { DepenseRow } from '../types/depense.types';
const columns: DataTableColumn<DepenseRow>[] = [
    { key: 'date', title: 'Date', sortable: true }, { key: 'category', title: 'Catégorie', sortable: true }, { key: 'property', title: 'Bien concerné', sortable: true }, { key: 'description', title: 'Description', sortable: true }, { key: 'amount', title: 'Montant', sortable: true }, { key: 'status', title: 'Statut', sortable: true, render: (row) => <Badge variant={row.status === 'Validée' ? 'success' : 'warning'}>{row.status}</Badge> },
];
export function DepensesTable({ rows }: { rows: DepenseRow[] }) { return <DataTable columns={columns} data={rows} pageSize={8} initialSortKey="date" />; }
