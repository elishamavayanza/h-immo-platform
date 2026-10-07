import { DataTable } from '../../../../components/Data/DataTable';
import type { DataTableColumn } from '../../../../hook-components/Data/DataTable';
import { Badge } from '../../../../components/UI/Badge';
import { PatronManagedTable, type PatronTableAction } from '../../../patron/shared/PatronManagedTable';
import type { LocataireRow } from '../types/locataire.types';

const ARCHIVE_ACTION: PatronTableAction<LocataireRow> = { id: 'archive', label: () => 'Archiver', apply: () => ({ status: 'Ancien' }), visible: (row) => row.status !== 'Ancien' };

const columns: DataTableColumn<LocataireRow>[] = [
    { key: 'name', title: 'Locataire', sortable: true, render: (item) => <div className="organization-property-name"><strong>{item.name}</strong><small>{item.email} · {item.phone}</small></div> },
    { key: 'property', title: 'Bien / unité', sortable: true }, { key: 'leaseEnd', title: 'Fin du bail', sortable: true },
    { key: 'balance', title: 'Solde', sortable: true },
    { key: 'status', title: 'Statut', sortable: true, render: (item) => <Badge variant={item.status === 'Actif' ? 'success' : item.status === 'En attente' ? 'warning' : 'secondary'}>{item.status}</Badge> },
];

export function LocatairesTable({ rows }: { rows: LocataireRow[] }) { return <PatronManagedTable rows={rows} columns={columns} title="un locataire" createLabel="Ajouter un locataire" initialSortKey="name" fields={[{ key: 'name', label: 'Nom complet', required: true }, { key: 'email', label: 'E-mail', required: true }, { key: 'phone', label: 'Téléphone' }, { key: 'city', label: 'Ville' }, { key: 'property', label: 'Bien / unité' }, { key: 'leaseEnd', label: 'Fin du bail' }, { key: 'balance', label: 'Solde' }, { key: 'status', label: 'Statut' }]} createRecord={(values) => ({ id: crypto.randomUUID(), name: values.name ?? '', email: values.email ?? '', phone: values.phone ?? '', city: values.city ?? '', property: values.property ?? '', leaseEnd: values.leaseEnd ?? '—', balance: values.balance ?? '0 USD', status: (values.status as LocataireRow['status']) ?? 'En attente' })} />; }
