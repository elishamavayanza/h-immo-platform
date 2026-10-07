import { InlineSummary } from '../../../shared/components/InlineSummary';
import type { UserRow } from '../types/user.types';

export function UsersStats({ users }: { users: UserRow[] }) {
    return <InlineSummary items={[
        { label: 'Utilisateurs', value: users.length },
        { label: 'Actifs', value: users.filter((item) => item.status === 'active').length },
        { label: 'Désactivés', value: users.filter((item) => item.status === 'inactive').length },
        { label: 'Administrateurs', value: users.filter((item) => item.role !== 'super_admin').length },
    ]} />;
}
