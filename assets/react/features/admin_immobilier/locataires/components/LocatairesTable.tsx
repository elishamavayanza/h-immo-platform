import { DataTable } from '../../../../components/Data/DataTable';
import type { DataTableColumn } from '../../../../hook-components/Data/DataTable';
import { Badge } from '../../../../components/UI/Badge';
import { PopoverMenu } from '../../../../components/UI/PopoverMenu';
import type { PopoverMenuItem } from '../../../../hook-components/UI/PopoverMenu';
import { formatUserDate } from '../../../../services/userPreferences';
import type { LocataireRow, TenantKind } from '../types/locataire.types';

const TYPE_LABEL: Record<TenantKind, string> = {
    individual: 'Personne physique',
    company: 'Personne morale',
};
const TYPE_VARIANT: Record<TenantKind, 'secondary' | 'info'> = {
    individual: 'secondary',
    company: 'info',
};

interface LocatairesTableProps {
    rows: LocataireRow[];
    onArchive: (row: LocataireRow) => void;
}

/**
 * Table des locataires : une seule action, Archiver (suppression logique
 * `ARCHIVE_TENANT`). Pas de suppression définitive côté UI : l'historique
 * comptable des baux doit rester consultable.
 */
export function LocatairesTable({ rows, onArchive }: LocatairesTableProps) {
    const columns: DataTableColumn<LocataireRow>[] = [
        {
            key: 'name',
            title: 'Locataire',
            sortable: true,
            render: (row) => <div className="organization-property-name"><strong>{row.name}</strong><small>{row.sublabel}</small></div>,
        },
        { key: 'type', title: 'Type', sortable: true, render: (row) => <Badge variant={TYPE_VARIANT[row.type]}>{TYPE_LABEL[row.type]}</Badge> },
        { key: 'unitReference', title: 'Unité', sortable: true, render: (row) => row.unitReference ?? '—' },
        { key: 'address', title: 'Adresse', sortable: true },
        { key: 'leaseEnd', title: 'Fin du bail', sortable: true, render: (row) => (row.leaseEnd ? formatUserDate(row.leaseEnd) : 'Aucun bail actif') },
        {
            key: 'actions',
            title: 'Actions',
            render: (row) => {
                const items: PopoverMenuItem[] = [
                    { id: 'archive', label: 'Archiver', icon: <span aria-hidden="true">🗄</span>, danger: true, onClick: () => onArchive(row) },
                ];

                return <PopoverMenu placement="bottom" offset={6} items={items} trigger={<span className="organization-row-actions" aria-label={`Actions pour ${row.name}`}><span aria-hidden="true">•••</span></span>} />;
            },
        },
    ];

    return <DataTable columns={columns} data={rows} pageSize={12} initialSortKey="name" />;
}
