import { DataTable } from '../../../../components/Data/DataTable';
import type { DataTableColumn } from '../../../../hook-components/Data/DataTable';
import { Badge } from '../../../../components/UI/Badge';
import { PatronManagedTable } from '../../../patron/shared/PatronManagedTable';
import type { PatrimoineRow } from '../types/patrimoine.types';

const columns: DataTableColumn<PatrimoineRow>[] = [
    { key: 'name', title: 'Bien immobilier', sortable: true, render: (item) => <div className="organization-property-name"><strong>{item.name}</strong><small>{item.address}</small></div> },
    { key: 'city', title: 'Ville', sortable: true }, { key: 'kind', title: 'Type', sortable: true },
    { key: 'units', title: 'Unités', sortable: true }, { key: 'occupancy', title: 'Occupation', sortable: true },
    { key: 'status', title: 'Statut', sortable: true, render: (item) => <Badge variant={item.status === 'Actif' ? 'success' : 'warning'}>{item.status}</Badge> },
];

export function PatrimoineTable({ rows }: { rows: PatrimoineRow[] }) { return <PatronManagedTable rows={rows} columns={columns} title="un bien" createLabel="Ajouter un bien" initialSortKey="name" fields={[{ key: 'name', label: 'Nom du bien', required: true }, { key: 'city', label: 'Ville', required: true }, { key: 'address', label: 'Adresse' }, { key: 'kind', label: 'Type' }, { key: 'units', label: 'Nombre d’unités', type: 'number' }, { key: 'occupancy', label: 'Occupation' }, { key: 'status', label: 'Statut' }]} createRecord={(values) => ({ id: crypto.randomUUID(), name: values.name ?? '', city: values.city ?? rows[0]?.city ?? '', address: values.address ?? '', kind: (values.kind as PatrimoineRow['kind']) ?? 'Bâtiment', units: Number(values.units) || 0, occupancy: values.occupancy ?? '—', status: (values.status as PatrimoineRow['status']) ?? 'Actif' })}  />; }
