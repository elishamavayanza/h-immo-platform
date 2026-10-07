import { DataTable } from '../../../../components/Data/DataTable';
import type { DataTableColumn } from '../../../../hook-components/Data/DataTable';
import { Badge } from '../../../../components/UI/Badge';
import type { LocataireRow } from '../types/locataire.types';

const columns: DataTableColumn<LocataireRow>[] = [
    { key: 'name', title: 'Locataire', sortable: true, render: (item) => <div className="organization-property-name"><strong>{item.name}</strong><small>{item.email} · {item.phone}</small></div> },
    { key: 'property', title: 'Bien / unité', sortable: true }, { key: 'leaseEnd', title: 'Fin du bail', sortable: true },
    { key: 'balance', title: 'Solde', sortable: true },
    { key: 'status', title: 'Statut', sortable: true, render: (item) => <Badge variant={item.status === 'Actif' ? 'success' : item.status === 'En attente' ? 'warning' : 'secondary'}>{item.status}</Badge> },
];

export function LocatairesTable({ rows }: { rows: LocataireRow[] }) { return <DataTable columns={columns} data={rows} pageSize={8} initialSortKey="name" />; }
