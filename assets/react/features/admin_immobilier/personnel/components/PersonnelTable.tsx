import { DataTable } from '../../../../components/Data/DataTable';
import type { DataTableColumn } from '../../../../hook-components/Data/DataTable';
import type { PersonnelRow } from '../types/personnel.types';

/**
 * Table du personnel : lecture seule.
 *
 * Fonction et ville proviennent de la dernière affectation. La création d'un
 * travailleur et le statut « Actif / Suspendu » de la maquette ne sont pas
 * branchés dans ce lot.
 */
export function PersonnelTable({ rows }: { rows: PersonnelRow[] }) {
    const columns: DataTableColumn<PersonnelRow>[] = [
        { key: 'name', title: 'Membre du personnel', sortable: true, render: (row) => <div className="organization-property-name"><strong>{row.name}</strong><small>{row.email ?? row.phone}</small></div> },
        { key: 'role', title: 'Fonction', sortable: true, render: (row) => row.role },
        { key: 'city', title: 'Ville', sortable: true, render: (row) => row.city },
        { key: 'assignments', title: 'Affectations', sortable: true },
        { key: 'phone', title: 'Téléphone', sortable: true },
    ];

    return <DataTable columns={columns} data={rows} pageSize={12} initialSortKey="name" />;
}
