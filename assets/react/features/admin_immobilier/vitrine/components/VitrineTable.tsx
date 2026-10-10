import { DataTable } from '../../../../components/Data/DataTable';
import type { DataTableColumn } from '../../../../hook-components/Data/DataTable';
import { Badge } from '../../../../components/UI/Badge';
import { PopoverMenu } from '../../../../components/UI/PopoverMenu';
import type { PopoverMenuItem } from '../../../../hook-components/UI/PopoverMenu';
import { formatMoney } from '../../../../../utils/format.utils';
import { canDo } from '../../../shared/permissions';
import type { OrganizationRole } from '../../../../../services/api/api.types';
import type { VitrineRow } from '../types/vitrine.types';

interface VitrineTableProps {
    rows: VitrineRow[];
    role: OrganizationRole | null;
    onTogglePublished: (row: VitrineRow) => void;
    onEdit: (row: VitrineRow) => void;
    onAddPhoto: (row: VitrineRow) => void;
    pendingId: string | null;
}

/**
 * Table des annonces vitrine.
 *
 * La publication bascule `isPublished` (`PATCH …/publish`). Une unité occupée
 * par un bail actif ne peut pas être publiée : l'action est désactivée côté
 * UI, le backend refusant de toute façon en 422.
 * Actions : Publier/Retirer, Modifier description, Gérer les photos.
 */
export function VitrineTable({ rows, role, onTogglePublished, onEdit, onAddPhoto, pendingId }: VitrineTableProps) {
    const columns: DataTableColumn<VitrineRow>[] = [
        { key: 'title', title: 'Annonce', sortable: true },
        { key: 'city', title: 'Ville', sortable: true },
        { key: 'type', title: 'Type de bien', sortable: true, render: (row) => row.type },
        { key: 'rent', title: 'Loyer affiché', sortable: true, render: (row) => formatMoney(row.rent, row.currency) },
        {
            key: 'isPublished',
            title: 'Statut',
            sortable: true,
            render: (row) => <Badge variant={row.isPublished ? 'success' : 'secondary'}>{row.isPublished ? 'Publiée' : 'Brouillon'}</Badge>,
        },
        {
            key: 'actions',
            title: 'Actions',
            render: (row) => {
                const canPublish = row.isPublished || !row.isOccupied;
                const label = row.isPublished ? 'Dépublier' : 'Publier';
                const items: PopoverMenuItem[] = [
                    {
                        id: 'toggle',
                        label: row.isOccupied && !row.isPublished ? 'Unité occupée' : label,
                        icon: <span aria-hidden="true">🌐</span>,
                        disabled: !canPublish || pendingId === row.id,
                        onClick: () => onTogglePublished(row),
                    },
                    ...(canDo(role, 'update_unit')
                        ? [{ id: 'edit', label: 'Modifier la description', icon: <span aria-hidden="true">✎</span>, onClick: () => onEdit(row) }]
                        : []),
                    ...(canDo(role, 'publish_listing')
                        ? [{ id: 'photos', label: 'Gérer les photos', icon: <span aria-hidden="true">📷</span>, onClick: () => { /* handled in page */ } }]
                        : []),
                ];

                if (items.length === 0) return '—';

                return <PopoverMenu placement="bottom" offset={6} items={items} trigger={<span className="organization-row-actions" aria-label={`Actions pour ${row.title}`}><span aria-hidden="true">•••</span></span>} />;
            },
        },
    ];

    return <DataTable columns={columns} data={rows} pageSize={12} initialSortKey="title" />;
}
