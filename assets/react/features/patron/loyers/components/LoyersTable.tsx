import { DataTable } from '../../../../components/Data/DataTable';
import type { DataTableColumn } from '../../../../hook-components/Data/DataTable';
import { Badge } from '../../../../components/UI/Badge';
import type { LoyerRow } from '../types/loyer.types';
import { PatronManagedTable } from '../../shared/PatronManagedTable';
const variants = { Payé: 'success', Partiel: 'info', 'En attente': 'warning', 'En retard': 'error' } as const;
const columns: DataTableColumn<LoyerRow>[] = [
    { key: 'tenant', title: 'Locataire', sortable: true }, { key: 'property', title: 'Bien / unité', sortable: true }, { key: 'period', title: 'Période', sortable: true }, { key: 'dueDate', title: 'Échéance', sortable: true }, { key: 'amount', title: 'Montant dû', sortable: true }, { key: 'status', title: 'Statut', sortable: true, render: (row) => <Badge variant={variants[row.status]}>{row.status}</Badge> },
];
export function LoyersTable({ rows }: { rows: LoyerRow[] }) { return <PatronManagedTable rows={rows} columns={columns} title="une échéance" createLabel="Créer une échéance" initialSortKey="dueDate" fields={[{ key: 'tenant', label: 'Locataire', required: true }, { key: 'property', label: 'Bien / unité', required: true }, { key: 'period', label: 'Période', required: true }, { key: 'dueDate', label: 'Date d’échéance', required: true }, { key: 'amount', label: 'Montant dû (devise incluse)', required: true }]} createRecord={(values) => ({ id: crypto.randomUUID(), tenant: values.tenant ?? '', city: '', property: values.property ?? '', period: values.period ?? '', dueDate: values.dueDate ?? '', amount: values.amount ?? '', status: 'En attente' as const })} />; }
