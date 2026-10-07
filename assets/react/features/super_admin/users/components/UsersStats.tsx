import { Card } from '../../../../components/UI/Card';
import type { UserRow } from '../types/user.types';

export function UsersStats({ users }: { users: UserRow[] }) {
    return <div className="sa-management-stats">
        <Card className="sa-management-stat" padding="medium"><span>Total utilisateurs</span><strong>{users.length}</strong><small>Comptes enregistrés</small></Card>
        <Card className="sa-management-stat" padding="medium"><span>Actifs</span><strong>{users.filter((item) => item.status === 'active').length}</strong><small>Accès opérationnel</small></Card>
        <Card className="sa-management-stat" padding="medium"><span>Invitations en attente</span><strong>{users.filter((item) => item.status === 'invited').length}</strong><small>À rejoindre la plateforme</small></Card>
        <Card className="sa-management-stat" padding="medium"><span>Administrateurs</span><strong>{users.filter((item) => item.role !== 'super_admin').length}</strong><small>Rôles organisationnels</small></Card>
    </div>;
}
