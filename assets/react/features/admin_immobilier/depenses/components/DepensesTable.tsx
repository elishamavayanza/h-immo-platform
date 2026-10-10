import { DataTable } from '../../../../components/Data/DataTable';
import type { DataTableColumn } from '../../../../hook-components/Data/DataTable';
import { formatUserDate } from '../../../../services/userPreferences';
import { formatMoney } from '../../../../../utils/format.utils';
import type { DepenseRow } from '../types/depense.types';

/**
 * Table des dépenses : lecture seule.
 *
 * La colonne « statut » de la maquette est supprimée : le backend n'expose
 * aucun statut de dépense (une correction se fait par contre-écriture). La
 * création d'une dépense n'est pas branchée dans ce lot.
 */
export function DepensesTable({ rows }: { rows: DepenseRow[] }) {
    const columns: DataTableColumn<DepenseRow>[] = [
        { key: 'date', title: 'Date', sortable: true, render: (row) => formatUserDate(row.date) },
        { key: 'category', title: 'Catégorie', sortable: true },
        { key: 'property', title: 'Bien concerné', sortable: true, render: (row) => row.property },
        { key: 'description', title: 'Description', sortable: true },
        { key: 'city', title: 'Ville', sortable: true },
        { key: 'amount', title: 'Montant', sortable: true, render: (row) => formatMoney(row.amount, row.currency) },
    ];

    return <DataTable columns={columns} data={rows} pageSize={12} initialSortKey="date" initialSortDirection="desc" />;
}
