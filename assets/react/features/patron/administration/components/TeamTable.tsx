import { DataTable } from '../../../../components/Data/DataTable';
import type { DataTableColumn } from '../../../../hook-components/Data/DataTable';
import { Badge } from '../../../../components/UI/Badge';
import type { TeamMember } from '../types/administration.types';
const variants = { Actif: 'success', 'Invitation envoyée': 'warning', Suspendu: 'secondary' } as const;
const columns: DataTableColumn<TeamMember>[] = [
    { key: 'name', title: 'Membre', sortable: true, render: (member) => <div className="organization-property-name"><strong>{member.name}</strong><small>{member.email}</small></div> }, { key: 'role', title: 'Rôle', sortable: true }, { key: 'scope', title: 'Périmètre', sortable: true }, { key: 'status', title: 'Statut', sortable: true, render: (member) => <Badge variant={variants[member.status]}>{member.status}</Badge> },
];
export function TeamTable({ rows }: { rows: TeamMember[] }) { return <DataTable columns={columns} data={rows} pageSize={8} initialSortKey="name" />; }
