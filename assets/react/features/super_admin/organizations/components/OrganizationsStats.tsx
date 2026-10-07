import { Card } from '../../../../components/UI/Card';
import type { OrganizationRow } from '../types/organization.types';

export function OrganizationsStats({ organizations }: { organizations: OrganizationRow[] }) {
    return <div className="sa-management-stats">
        <Card className="sa-management-stat" padding="medium"><span>Total organisations</span><strong>{organizations.length}</strong><small>Sur la plateforme</small></Card>
        <Card className="sa-management-stat" padding="medium"><span>Actives</span><strong>{organizations.filter((item) => item.status === 'active').length}</strong><small>Accès opérationnel</small></Card>
        <Card className="sa-management-stat" padding="medium"><span>En essai</span><strong>{organizations.filter((item) => item.status === 'trial').length}</strong><small>Période de découverte</small></Card>
        <Card className="sa-management-stat" padding="medium"><span>Utilisateurs</span><strong>{organizations.reduce((sum, item) => sum + item.members, 0)}</strong><small>Toutes organisations</small></Card>
    </div>;
}
