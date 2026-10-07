import { DataTable } from '../../../../components/Data/DataTable';
import type { DataTableColumn } from '../../../../hook-components/Data/DataTable';
import { Badge } from '../../../../components/UI/Badge';
import type { VitrineListing } from '../types/vitrine.types';
import { PatronManagedTable } from '../../shared/PatronManagedTable';
const variants = { Publiée: 'success', Brouillon: 'secondary', 'À compléter': 'warning' } as const;
const columns: DataTableColumn<VitrineListing>[] = [
    { key: 'title', title: 'Annonce', sortable: true }, { key: 'city', title: 'Ville', sortable: true }, { key: 'type', title: 'Type de bien', sortable: true }, { key: 'rent', title: 'Loyer affiché', sortable: true }, { key: 'views', title: 'Vues', sortable: true }, { key: 'status', title: 'Statut', sortable: true, render: (row) => <Badge variant={variants[row.status]}>{row.status}</Badge> },
];
export function VitrineTable({ rows }: { rows: VitrineListing[] }) { return <PatronManagedTable rows={rows} columns={columns} title="une annonce" createLabel="Ajouter une annonce" initialSortKey="title" fields={[{ key: 'title', label: 'Titre', required: true }, { key: 'city', label: 'Ville', required: true }, { key: 'type', label: 'Type de bien' }, { key: 'rent', label: 'Loyer affiché (devise incluse)' }, { key: 'views', label: 'Vues', type: 'number' }, { key: 'status', label: 'Statut' }]} createRecord={(values) => ({ id: crypto.randomUUID(), title: values.title ?? '', city: values.city ?? '', type: values.type ?? '', rent: values.rent ?? '', views: Number(values.views) || 0, status: (values.status as VitrineListing['status']) ?? 'Brouillon' })} toggleStatus={(row) => ({ status: (row.status === 'Publiée' ? 'Brouillon' : 'Publiée') as VitrineListing['status'] })} statusLabel={(row) => row.status === 'Publiée' ? 'Dépublier' : 'Publier'} />; }
