import { Badge } from '../../../../components/UI/Badge';
import type { OrganizationSummary, OrganizationStatus } from '../types';

const STATUS_LABEL: Record<OrganizationStatus, string> = {
    active: 'Actif',
    suspended: 'Suspendue',
    inactive: 'Inactive',
};

const STATUS_VARIANT: Record<OrganizationStatus, 'success' | 'info' | 'error'> = {
    active: 'success',
    suspended: 'error',
    inactive: 'info',
};

export interface OrganizationsTableProps {
    organizations: OrganizationSummary[];
}

export function OrganizationsTable({ organizations }: OrganizationsTableProps) {
    return (
        <div className="sa-table-wrap">
            <table className="sa-table">
                <thead>
                <tr>
                    <th>Organisation</th>
                    <th>Plan</th>
                    <th className="is-num">Utilisateurs</th>
                    <th className="is-num">Propriétés</th>
                    <th>Statut</th>
                </tr>
                </thead>
                <tbody>
                {organizations.map((org) => (
                    <tr key={org.id}>
                        <td>
                            <div className="sa-table__org">
                                    <span className="sa-table__logo">
                                        {org.name.charAt(0)}
                                    </span>
                                <div>
                                    <div className="sa-table__name">{org.name}</div>
                                    <div className="sa-table__meta">
                                        {org.city} · {org.slug}
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span className="sa-plan">{org.plan}</span>
                        </td>
                        <td className="is-num">{org.users}</td>
                        <td className="is-num">{org.properties}</td>
                        <td>
                            <Badge variant={STATUS_VARIANT[org.status]}>
                                {STATUS_LABEL[org.status]}
                            </Badge>
                        </td>
                    </tr>
                ))}
                </tbody>
            </table>
        </div>
    );
}
