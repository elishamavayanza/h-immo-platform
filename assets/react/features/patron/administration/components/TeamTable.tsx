import { DataTable } from '../../../../components/Data/DataTable';
import type { DataTableColumn } from '../../../../hook-components/Data/DataTable';
import { Badge } from '../../../../components/UI/Badge';
import { Icon } from '../../../../components/UI/Icon/Icon';
import { PopoverMenu } from '../../../../components/UI/PopoverMenu';
import type { PopoverMenuItem } from '../../../../hook-components/UI/PopoverMenu';
import type { OrganizationRole } from '../../../../../services/api/api.types';
import type { TeamMember } from '../types/administration.types';

const ROLE_LABEL: Record<OrganizationRole, string> = {
    patron: 'Patron',
    admin_immobilier: 'Admin immobilier',
    admin_ville: 'Admin ville',
};
const ROLE_VARIANT: Record<OrganizationRole, 'primary' | 'secondary' | 'info'> = {
    patron: 'primary',
    admin_immobilier: 'info',
    admin_ville: 'info',
};

interface TeamTableProps {
    rows: TeamMember[];
    onEdit: (member: TeamMember) => void;
    onSuspend: (member: TeamMember) => void;
}

/**
 * Table des membres de l'équipe (espace PATRON), branchée sur l'API.
 *
 * Deux actions seulement : Modifier (fiche) et Suspendre (désactivation +
 * email). Pas de « Supprimer » : les comptes et leurs traces comptables sont
 * conservés, une réintégration passant par la modification/re-activation.
 * Suspendre est masqué sur le compte de l'appelant (auto-suspension refusée
 * en 422) et sur les comptes déjà désactivés.
 */
export function TeamTable({ rows, onEdit, onSuspend }: TeamTableProps) {
    const columns: DataTableColumn<TeamMember>[] = [
        {
            key: 'name',
            title: 'Membre',
            sortable: true,
            render: (member) => <div className="organization-property-name"><strong>{member.name}</strong><small>{member.email}</small></div>,
        },
        { key: 'role', title: 'Rôle', sortable: true, render: (member) => <Badge variant={ROLE_VARIANT[member.role]}>{ROLE_LABEL[member.role]}</Badge> },
        { key: 'phone', title: 'Téléphone', sortable: false, render: (member) => member.phone || '—' },
        { key: 'status', title: 'Statut', sortable: true, render: (member) => <Badge variant={member.status === 'active' ? 'success' : 'secondary'}>{member.status === 'active' ? 'Actif' : 'Désactivé'}</Badge> },
        {
            key: 'actions',
            title: 'Actions',
            render: (member) => {
                const items: PopoverMenuItem[] = [
                    { id: 'edit', label: 'Modifier', icon: <Icon name="edit" />, onClick: () => onEdit(member) },
                    ...(member.status === 'active' && !member.isSelf
                        ? [{ id: 'suspend', label: 'Suspendre', icon: <Icon name="power" />, danger: true, onClick: () => onSuspend(member) }]
                        : []),
                ];

                return <PopoverMenu placement="bottom" offset={6} items={items} trigger={<span className="organization-row-actions" aria-label={`Actions pour ${member.name}`}><Icon name="more" /></span>} />;
            },
        },
    ];

    return <DataTable columns={columns} data={rows} pageSize={12} initialSortKey="name" />;
}
