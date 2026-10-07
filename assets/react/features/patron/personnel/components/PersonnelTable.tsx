import { DataTable } from '../../../../components/Data/DataTable';
import type { DataTableColumn } from '../../../../hook-components/Data/DataTable';
import { Badge } from '../../../../components/UI/Badge';
import type { PersonnelRow } from '../types/personnel.types';
const columns: DataTableColumn<PersonnelRow>[] = [
    { key: 'name', title: 'Membre du personnel', sortable: true }, { key: 'role', title: 'Fonction', sortable: true }, { key: 'city', title: 'Ville', sortable: true }, { key: 'assignments', title: 'Affectations', sortable: true }, { key: 'phone', title: 'Téléphone', sortable: true }, { key: 'status', title: 'Statut', sortable: true, render: (row) => <Badge variant={row.status === 'Actif' ? 'success' : 'secondary'}>{row.status}</Badge> },
];
export function PersonnelTable({ rows }: { rows: PersonnelRow[] }) { return <DataTable columns={columns} data={rows} pageSize={8} initialSortKey="name" />; }
