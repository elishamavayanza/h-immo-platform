import { DataTable } from '../../../../components/Data/DataTable';
import type { DataTableColumn } from '../../../../hook-components/Data/DataTable';
import { Badge } from '../../../../components/UI/Badge';
import type { LoyerRow } from '../types/loyer.types';
const variants = { Payé: 'success', Partiel: 'info', 'En attente': 'warning', 'En retard': 'error' } as const;
const columns: DataTableColumn<LoyerRow>[] = [
    { key: 'tenant', title: 'Locataire', sortable: true }, { key: 'property', title: 'Bien / unité', sortable: true }, { key: 'period', title: 'Période', sortable: true }, { key: 'dueDate', title: 'Échéance', sortable: true }, { key: 'amount', title: 'Montant dû', sortable: true }, { key: 'status', title: 'Statut', sortable: true, render: (row) => <Badge variant={variants[row.status]}>{row.status}</Badge> },
];
export function LoyersTable({ rows }: { rows: LoyerRow[] }) { return <DataTable columns={columns} data={rows} pageSize={8} initialSortKey="dueDate" />; }
