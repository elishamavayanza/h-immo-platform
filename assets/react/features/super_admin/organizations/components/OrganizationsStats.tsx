import { Card } from '../../../../components/UI/Card';
import type { OrganizationRow } from '../types/organization.types';

export function OrganizationsStats({ organizations }: { organizations: OrganizationRow[] }) {
    return <div className="sa-management-stats">
        <Card className="sa-management-stat" padding="medium"><span>Total organisations</span><strong>{organizations.length}</strong><small>Sur la plateforme</small></Card>
        <Card className="sa-management-stat" padding="medium"><span>Actives</span><strong>{organizations.filter((item) => item.status === 'active').length}</strong><small>Accès opérationnel</small></Card>
        <Card className="sa-management-stat" padding="medium"><span>Suspendues</span><strong>{organizations.filter((item) => item.status === 'suspended').length}</strong><small>Accès temporairement désactivé</small></Card>
        <Card className="sa-management-stat" padding="medium"><span>Inactives</span><strong>{organizations.filter((item) => item.status === 'inactive').length}</strong><small>Statut enregistré</small></Card>
    </div>;
}
