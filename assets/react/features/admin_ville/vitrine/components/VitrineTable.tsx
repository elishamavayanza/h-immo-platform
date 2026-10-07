import { DataTable } from '../../../../components/Data/DataTable';
import type { DataTableColumn } from '../../../../hook-components/Data/DataTable';
import { Badge } from '../../../../components/UI/Badge';
import type { VitrineListing } from '../types/vitrine.types';
const variants = { Publiée: 'success', Brouillon: 'secondary', 'À compléter': 'warning' } as const;
const columns: DataTableColumn<VitrineListing>[] = [
    { key: 'title', title: 'Annonce', sortable: true }, { key: 'city', title: 'Ville', sortable: true }, { key: 'type', title: 'Type de bien', sortable: true }, { key: 'rent', title: 'Loyer affiché', sortable: true }, { key: 'views', title: 'Vues', sortable: true }, { key: 'status', title: 'Statut', sortable: true, render: (row) => <Badge variant={variants[row.status]}>{row.status}</Badge> },
];
export function VitrineTable({ rows }: { rows: VitrineListing[] }) { return <DataTable columns={columns} data={rows} pageSize={8} initialSortKey="title" />; }
