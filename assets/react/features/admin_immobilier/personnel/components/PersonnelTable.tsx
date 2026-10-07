import { DataTable } from '../../../../components/Data/DataTable';
import type { DataTableColumn } from '../../../../hook-components/Data/DataTable';
import { Badge } from '../../../../components/UI/Badge';
import { PatronManagedTable } from '../../../patron/shared/PatronManagedTable';
import type { PersonnelRow } from '../types/personnel.types';
const columns: DataTableColumn<PersonnelRow>[] = [
    { key: 'name', title: 'Membre du personnel', sortable: true }, { key: 'role', title: 'Fonction', sortable: true }, { key: 'city', title: 'Ville', sortable: true }, { key: 'assignments', title: 'Affectations', sortable: true }, { key: 'phone', title: 'Téléphone', sortable: true }, { key: 'status', title: 'Statut', sortable: true, render: (row) => <Badge variant={row.status === 'Actif' ? 'success' : 'secondary'}>{row.status}</Badge> },
];
export function PersonnelTable({ rows }: { rows: PersonnelRow[] }) { return <PatronManagedTable rows={rows} columns={columns} title="un membre du personnel" createLabel="Ajouter un membre" initialSortKey="name" fields={[{ key: 'name', label: 'Nom complet', required: true }, { key: 'role', label: 'Fonction', required: true }, { key: 'city', label: 'Ville' }, { key: 'assignments', label: 'Affectations', type: 'number' }, { key: 'phone', label: 'Téléphone' }, { key: 'status', label: 'Statut' }]} createRecord={(values) => ({ id: crypto.randomUUID(), name: values.name ?? '', role: values.role ?? '', city: values.city ?? '', assignments: Number(values.assignments) || 0, phone: values.phone ?? '', status: (values.status as PersonnelRow['status']) ?? 'Actif' })}  />; }
