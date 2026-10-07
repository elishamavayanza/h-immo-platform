import { DataTable } from '../../../../components/Data/DataTable';
import type { DataTableColumn } from '../../../../hook-components/Data/DataTable';
import { Badge } from '../../../../components/UI/Badge';
import type { PatrimoineRow } from '../types/patrimoine.types';

const columns: DataTableColumn<PatrimoineRow>[] = [
    { key: 'name', title: 'Bien immobilier', sortable: true, render: (item) => <div className="organization-property-name"><strong>{item.name}</strong><small>{item.address}</small></div> },
    { key: 'city', title: 'Ville', sortable: true }, { key: 'kind', title: 'Type', sortable: true },
    { key: 'units', title: 'Unités', sortable: true }, { key: 'occupancy', title: 'Occupation', sortable: true },
    { key: 'status', title: 'Statut', sortable: true, render: (item) => <Badge variant={item.status === 'Actif' ? 'success' : 'warning'}>{item.status}</Badge> },
];

export function PatrimoineTable({ rows }: { rows: PatrimoineRow[] }) { return <DataTable columns={columns} data={rows} pageSize={8} initialSortKey="name" />; }
