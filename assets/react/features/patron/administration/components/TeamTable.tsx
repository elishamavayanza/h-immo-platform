import { DataTable } from '../../../../components/Data/DataTable';
import type { DataTableColumn } from '../../../../hook-components/Data/DataTable';
import { Badge } from '../../../../components/UI/Badge';
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
}

/**
 * Table des membres de l'équipe (espace PATRON), branchée sur l'API.
 *
 * Le PATRON consulte l’équipe et peut inviter les rôles autorisés. La matrice
 * backend lui refuse la modification des comptes et des affectations : aucune
 * action de ligne ne doit donc être affichée ici.
 */
export function TeamTable({ rows }: TeamTableProps) {
    const columns: DataTableColumn<TeamMember>[] = [
        {
            key: 'name',
            title: 'Membre',
            sortable: true,
            render: (member) => <div className="organization-property-name"><strong>{member.name}</strong><small>{member.email}</small></div>,
        },
        { key: 'role', title: 'Rôle', sortable: true, render: (member) => <Badge variant={ROLE_VARIANT[member.role]}>{ROLE_LABEL[member.role]}</Badge> },
        { key: 'cities', title: 'Villes attribuées', sortable: false, render: (member) => member.cityNames?.join(', ') || '—' },
        { key: 'status', title: 'Statut', sortable: true, render: (member) => <Badge variant={member.status === 'active' ? 'success' : 'secondary'}>{member.status === 'active' ? 'Actif' : 'Désactivé'}</Badge> },
    ];

    return <DataTable columns={columns} data={rows} pageSize={12} initialSortKey="name" />;
}
