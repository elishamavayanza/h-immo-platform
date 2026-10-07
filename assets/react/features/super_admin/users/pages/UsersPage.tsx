import { useState, type FormEvent } from 'react';

import { DataTable } from '../../../../components/Data/DataTable';
import type { DataTableColumn } from '../../../../hook-components/Data/DataTable';
import { FormField } from '../../../../components/Forms/FormField';
import { SearchInput } from '../../../../components/Forms/SearchInput';
import { Select } from '../../../../components/Forms/Select';
import { Badge } from '../../../../components/UI/Badge';
import { Button } from '../../../../components/UI/Button';
import { Card } from '../../../../components/UI/Card';
import { IconButton } from '../../../../components/UI/IconButton';
import { Modal } from '../../../../components/UI/Modal';
import { Input } from '../../../../components/Forms/Input';
import { Avatar } from '../../../../components/UI/Avatar';
import { PlatformPageHeader } from '../../shared/PlatformPageHeader';
import { UsersStats } from '../components/UsersStats';
import type { OrganizationRole } from '../../../../../services/api/api.types';
import type { UserRow, UserStatus } from '../types/user.types';
import { useUsers } from '../hooks/useUsers';
import '../../../../../styles/pages/super_admin/users/_users.scss';

const ROLE_LABEL: Record<UserRow['role'], string> = { super_admin: 'Super admin', patron: 'Patron', admin_immobilier: 'Admin immobilier', admin_ville: 'Admin ville' };
const ROLE_VARIANT: Record<UserRow['role'], 'primary' | 'secondary' | 'info'> = { super_admin: 'primary', patron: 'secondary', admin_immobilier: 'info', admin_ville: 'info' };
const STATUS_LABEL: Record<UserStatus, string> = { active: 'Actif', invited: 'Invité', suspended: 'Suspendu' };
const STATUS_VARIANT: Record<UserStatus, 'success' | 'warning' | 'error'> = { active: 'success', invited: 'warning', suspended: 'error' };
const ACTION_ICON = <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.5" /><circle cx="12" cy="12" r="1.5" /><circle cx="19" cy="12" r="1.5" /></svg>;

export function UsersPage() {
    const { users, filteredUsers, search, setSearch, roleFilter, setRoleFilter, statusFilter, setStatusFilter, addUser } = useUsers();
    const [isInviteOpen, setInviteOpen] = useState(false);
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [organization, setOrganization] = useState('Kinshasa Immo Group');
    const [role, setRole] = useState<OrganizationRole>('admin_immobilier');

    const columns: DataTableColumn<UserRow>[] = [
        { key: 'name', title: 'Utilisateur', sortable: true, render: (user) => <div className="sa-management-identity"><Avatar name={user.name} size="small" /><span><strong>{user.name}</strong><small>{user.email}</small></span></div> },
        { key: 'organization', title: 'Organisation', sortable: true },
        { key: 'role', title: 'Rôle', sortable: true, render: (user) => <Badge variant={ROLE_VARIANT[user.role]}>{ROLE_LABEL[user.role]}</Badge> },
        { key: 'status', title: 'Statut', sortable: true, render: (user) => <Badge variant={STATUS_VARIANT[user.status]}>{STATUS_LABEL[user.status]}</Badge> },
        { key: 'lastActivity', title: 'Dernière activité', sortable: true },
        { key: 'actions', title: '', render: (user) => <IconButton variant="ghost" ariaLabel={`Actions pour ${user.name}`} icon={ACTION_ICON} /> },
    ];

    const handleInvite = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!name.trim() || !email.trim()) return;
        addUser({ name: name.trim(), email: email.trim(), organization, role, status: 'invited' });
        setName('');
        setEmail('');
        setInviteOpen(false);
    };

    return (
        <div className="sa-dashboard sa-management-page">
            <PlatformPageHeader
                title="Utilisateurs"
                description="Consultez les comptes et gérez les accès aux organisations de la plateforme."
                icon="users"
                action={<Button icon={<span aria-hidden="true">＋</span>} onClick={() => setInviteOpen(true)}>Inviter un utilisateur</Button>}
            />

            <UsersStats users={users} />

            <Card className="sa-management-table-card" padding="medium">
                <div className="sa-management-toolbar">
                    <div><h2>Comptes utilisateurs</h2><p>Les données affichées sont une maquette locale.</p></div>
                    <div className="sa-management-filters sa-management-filters--users">
                        <SearchInput value={search} onSearch={setSearch} placeholder="Rechercher un utilisateur..." fullWidth />
                        <Select aria-label="Filtrer par rôle" value={roleFilter} onChange={(event) => setRoleFilter(event.target.value)} options={[{ value: 'all', label: 'Tous les rôles' }, ...Object.entries(ROLE_LABEL).map(([value, label]) => ({ value, label }))]} />
                        <Select aria-label="Filtrer par statut" value={statusFilter} onChange={(event) => setStatusFilter(event.target.value as 'all' | UserStatus)} options={[{ value: 'all', label: 'Tous les statuts' }, { value: 'active', label: 'Actif' }, { value: 'invited', label: 'Invité' }, { value: 'suspended', label: 'Suspendu' }]} />
                    </div>
                </div>
                <DataTable columns={columns} data={filteredUsers} pageSize={6} initialSortKey="name" />
            </Card>

            <Modal isOpen={isInviteOpen} onClose={() => setInviteOpen(false)} title="Inviter un utilisateur" size="medium" footer={<><Button variant="outline" onClick={() => setInviteOpen(false)}>Annuler</Button><Button type="submit" form="user-invite-form">Envoyer l’invitation</Button></>}>
                <form id="user-invite-form" className="sa-management-form" onSubmit={handleInvite}>
                    <p className="sa-management-form__hint">L’invitation est simulée et reste dans cette maquette locale.</p>
                    <FormField label="Nom complet" htmlFor="user-name" required><Input id="user-name" value={name} onChange={(event) => setName(event.target.value)} required placeholder="Ex. Alex Ilunga" fullWidth /></FormField>
                    <FormField label="Adresse e-mail" htmlFor="user-email" required><Input id="user-email" type="email" value={email} onChange={(event) => setEmail(event.target.value)} required placeholder="nom@organisation.cd" fullWidth /></FormField>
                    <FormField label="Organisation" htmlFor="user-organization" required><Select id="user-organization" value={organization} onChange={(event) => setOrganization(event.target.value)} options={['Kinshasa Immo Group', 'Lubumbashi Résidences', 'Goma Patrimoine', 'Matadi Logements'].map((value) => ({ value, label: value }))} fullWidth /></FormField>
                    <FormField label="Rôle" htmlFor="user-role"><Select id="user-role" value={role} onChange={(event) => setRole(event.target.value as OrganizationRole)} options={(['patron', 'admin_immobilier', 'admin_ville'] as const).map((value) => ({ value, label: ROLE_LABEL[value] }))} fullWidth /></FormField>
                </form>
            </Modal>
        </div>
    );
}
