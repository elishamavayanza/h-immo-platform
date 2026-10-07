import { DataTable } from '../../../../components/Data/DataTable';
import type { DataTableColumn } from '../../../../hook-components/Data/DataTable';
import { Badge } from '../../../../components/UI/Badge';
import type { TeamMember } from '../types/administration.types';
import { PatronManagedTable } from '../../shared/PatronManagedTable';
const variants = { Actif: 'success', 'Invitation envoyée': 'warning', Suspendu: 'secondary' } as const;
const columns: DataTableColumn<TeamMember>[] = [
    { key: 'name', title: 'Membre', sortable: true, render: (member) => <div className="organization-property-name"><strong>{member.name}</strong><small>{member.email}</small></div> }, { key: 'role', title: 'Rôle', sortable: true }, { key: 'scope', title: 'Périmètre', sortable: true }, { key: 'status', title: 'Statut', sortable: true, render: (member) => <Badge variant={variants[member.status]}>{member.status}</Badge> },
];
export function TeamTable({ rows }: { rows: TeamMember[] }) { return <PatronManagedTable rows={rows} columns={columns} title="un membre" createLabel="Inviter un membre" initialSortKey="name" fields={[{ key: 'name', label: 'Nom complet', required: true }, { key: 'email', label: 'Adresse e-mail', required: true }, { key: 'role', label: 'Rôle' }, { key: 'scope', label: 'Villes autorisées' }, { key: 'status', label: 'Statut' }]} createRecord={(values) => ({ id: crypto.randomUUID(), name: values.name ?? '', email: values.email ?? '', role: (values.role as TeamMember['role']) ?? 'Admin immobilier', scope: values.scope ?? '', status: 'Invitation envoyée' as const })} />; }
