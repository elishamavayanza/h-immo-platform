import { DataTable } from '../../../../components/Data/DataTable';
import type { DataTableColumn } from '../../../../hook-components/Data/DataTable';
import { formatInteger, formatPercent } from '../../../../../utils/format.utils';
import type { PatrimoineRow } from '../types/patrimoine.types';

/**
 * Table du patrimoine : lecture seule.
 *
 * Contrairement à la maquette, pas de création/modification locale ici —
 * la gestion des villes/parcelles/immeubles appartient aux écrans de
 * référence (PATRON / édition dédiée), cette page est un suivi.
 */
export function PatrimoineTable({ rows }: { rows: PatrimoineRow[] }) {
    const columns: DataTableColumn<PatrimoineRow>[] = [
        {
            key: 'name',
            title: 'Bien immobilier',
            sortable: true,
            render: (row) => <div className="organization-property-name"><strong>{row.name}</strong><small>{row.sublabel}</small></div>,
        },
        { key: 'city', title: 'Ville', sortable: true },
        { key: 'kind', title: 'Type', sortable: true },
        { key: 'units', title: 'Unités', sortable: true, render: (row) => formatInteger(row.units) },
        {
            key: 'occupancyRate',
            title: 'Occupation',
            sortable: true,
            render: (row) => (row.occupancyRate === null ? '—' : formatPercent(row.occupancyRate)),
        },
    ];

    return <DataTable columns={columns} data={rows} pageSize={12} initialSortKey="name" />;
}
