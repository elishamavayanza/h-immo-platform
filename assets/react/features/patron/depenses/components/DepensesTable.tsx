import { DataTable } from '../../../../components/Data/DataTable';
import type { DataTableColumn } from '../../../../hook-components/Data/DataTable';
import { Badge } from '../../../../components/UI/Badge';
import type { DepenseRow } from '../types/depense.types';
import { PatronManagedTable } from '../../shared/PatronManagedTable';
const columns: DataTableColumn<DepenseRow>[] = [
    { key: 'date', title: 'Date', sortable: true }, { key: 'category', title: 'Catégorie', sortable: true }, { key: 'property', title: 'Bien concerné', sortable: true }, { key: 'description', title: 'Description', sortable: true }, { key: 'amount', title: 'Montant', sortable: true }, { key: 'status', title: 'Statut', sortable: true, render: (row) => <Badge variant={row.status === 'Validée' ? 'success' : 'warning'}>{row.status}</Badge> },
];
export function DepensesTable({ rows }: { rows: DepenseRow[] }) { return <PatronManagedTable rows={rows} columns={columns} title="une dépense" createLabel="Ajouter une dépense" initialSortKey="date" fields={[{ key: 'date', label: 'Date', required: true }, { key: 'category', label: 'Catégorie', required: true }, { key: 'property', label: 'Bien concerné' }, { key: 'description', label: 'Description', required: true }, { key: 'amount', label: 'Montant (devise incluse)', required: true }, { key: 'status', label: 'Statut' }]} createRecord={(values) => ({ id: crypto.randomUUID(), date: values.date ?? '', city: '', category: values.category ?? '', property: values.property ?? '', description: values.description ?? '', amount: values.amount ?? '', status: (values.status as DepenseRow['status']) ?? 'À valider' })} />; }
