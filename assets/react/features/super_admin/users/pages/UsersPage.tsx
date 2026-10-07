import { useState, type FormEvent } from 'react';
import { DataTable } from '../../../../components/Data/DataTable';
import type { DataTableColumn } from '../../../../hook-components/Data/DataTable';
import { FormField } from '../../../../components/Forms/FormField';
import { SearchInput } from '../../../../components/Forms/SearchInput';
import { Select } from '../../../../components/Forms/Select';
import { Badge } from '../../../../components/UI/Badge';
import { Button } from '../../../../components/UI/Button';
import { Card } from '../../../../components/UI/Card';
import { ConfirmDialog } from '../../../../components/UI/ConfirmDialog';
import { Modal } from '../../../../components/UI/Modal';
import { Input } from '../../../../components/Forms/Input';
import { Avatar } from '../../../../components/UI/Avatar';
import { PlatformPageHeader } from '../../shared/PlatformPageHeader';
import { RowActions } from '../../shared/RowActions';
import { INITIAL_ORGANIZATIONS } from '../../organizations/services/organizationsService';
import { UsersStats } from '../components/UsersStats';
import type { UserRow, UserStatus } from '../types/user.types';
import { useUsers } from '../hooks/useUsers';
import '../../../../../styles/pages/super_admin/users/_users.scss';

const ROLE_LABEL: Record<UserRow['role'], string> = { super_admin: 'Super admin', patron: 'Patron', admin_immobilier: 'Admin immobilier', admin_ville: 'Admin ville' };
const ROLE_VARIANT: Record<UserRow['role'], 'primary' | 'secondary' | 'info'> = { super_admin: 'primary', patron: 'secondary', admin_immobilier: 'info', admin_ville: 'info' };
const STATUS_LABEL: Record<UserStatus, string> = { active: 'Actif', inactive: 'Désactivé' };
const STATUS_VARIANT: Record<UserStatus, 'success' | 'error'> = { active: 'success', inactive: 'error' };
type UserForm = { firstName: string; lastName: string; email: string; phone: string; organizationUuid: string; role: UserRow['role']; password: string };
const EMPTY_FORM: UserForm = { firstName: '', lastName: '', email: '', phone: '', organizationUuid: INITIAL_ORGANIZATIONS[0].id, role: 'admin_immobilier', password: '' };

export function UsersPage() {
    const { users, filteredUsers, search, setSearch, roleFilter, setRoleFilter, statusFilter, setStatusFilter, addUser, updateUser, deleteUser } = useUsers();
    const [isFormOpen, setFormOpen] = useState(false);
    const [editingUser, setEditingUser] = useState<UserRow | null>(null);
    const [form, setForm] = useState(EMPTY_FORM);
    const [pendingAction, setPendingAction] = useState<{ user: UserRow; kind: 'toggle' | 'delete' } | null>(null);
    const change = (key: keyof UserForm, value: string) => setForm((current) => ({ ...current, [key]: value }));

    const openCreate = () => { setEditingUser(null); setForm(EMPTY_FORM); setFormOpen(true); };
    const openEdit = (user: UserRow) => {
        const [firstName = '', ...lastName] = user.name.split(' ');
        setEditingUser(user);
        setForm({ ...EMPTY_FORM, firstName, lastName: lastName.join(' '), email: user.email, phone: user.phone ?? '', organizationUuid: user.organizationUuid ?? INITIAL_ORGANIZATIONS[0].id, role: user.role });
        setFormOpen(true);
    };

    const columns: DataTableColumn<UserRow>[] = [
        { key: 'name', title: 'Utilisateur', sortable: true, render: (user) => <div className="sa-management-identity"><Avatar name={user.name} size="small" /><span><strong>{user.name}</strong><small>{user.email}</small></span></div> },
        { key: 'organization', title: 'Organisation', sortable: true },
        { key: 'role', title: 'Rôle organisationnel', sortable: true, render: (user) => <Badge variant={ROLE_VARIANT[user.role]}>{ROLE_LABEL[user.role]}</Badge> },
        { key: 'status', title: 'Compte', sortable: true, render: (user) => <Badge variant={STATUS_VARIANT[user.status]}>{STATUS_LABEL[user.status]}</Badge> },
        { key: 'lastLoginAt', title: 'Dernière connexion', sortable: true, render: (user) => user.lastLoginAt ? new Date(user.lastLoginAt).toLocaleString('fr-FR') : 'Jamais connecté' },
        { key: 'actions', title: 'Actions', render: (user) => <RowActions label={user.name} isActive={user.status === 'active'} onEdit={() => openEdit(user)} onToggleActive={() => setPendingAction({ user, kind: 'toggle' })} onDelete={() => setPendingAction({ user, kind: 'delete' })} /> },
    ];

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const name = `${form.firstName.trim()} ${form.lastName.trim()}`.trim();
        if (editingUser) {
            if (form.role === 'super_admin') {
                updateUser(editingUser.id, { name, email: form.email.trim(), phone: form.phone.trim() || null, organization: 'Plateforme', organizationUuid: null, organizationUserUuid: null, role: 'super_admin' });
            } else {
                const organization = INITIAL_ORGANIZATIONS.find((item) => item.id === form.organizationUuid);
                const changedOrganization = editingUser.organizationUuid !== form.organizationUuid;
                updateUser(editingUser.id, { name, email: form.email.trim(), phone: form.phone.trim() || null, organizationUuid: form.organizationUuid, organizationUserUuid: changedOrganization ? crypto.randomUUID() : editingUser.organizationUserUuid, organization: organization?.name ?? editingUser.organization, role: form.role });
            }
        } else {
            const organization = form.role === 'super_admin' ? null : INITIAL_ORGANIZATIONS.find((item) => item.id === form.organizationUuid);
            addUser({ name, email: form.email.trim(), phone: form.phone.trim() || null, organizationUuid: organization?.id ?? null, organizationUserUuid: organization ? crypto.randomUUID() : null, organization: organization?.name ?? 'Plateforme', role: form.role, status: 'active' });
        }
        setFormOpen(false);
    };

    const confirmPendingAction = () => {
        if (!pendingAction) return;
        if (pendingAction.kind === 'delete') deleteUser(pendingAction.user.id);
        else updateUser(pendingAction.user.id, { status: pendingAction.user.status === 'active' ? 'inactive' : 'active' });
        setPendingAction(null);
    };

    return <div className="sa-dashboard sa-management-page">
        <PlatformPageHeader title="Utilisateurs" description="Gérez les comptes, leur état et leurs rôles dans les organisations." icon="users" action={<Button icon={<span aria-hidden="true">＋</span>} onClick={openCreate}>Nouvel utilisateur</Button>} />
        <UsersStats users={users} />
        <Card className="sa-management-table-card" padding="medium">
            <div className="sa-management-toolbar"><div><h2>Comptes utilisateurs</h2><p>Les actions sont simulées ; la maquette reprend UserRequest et les associations d’organisation.</p></div><div className="sa-management-filters sa-management-filters--users"><SearchInput value={search} onSearch={setSearch} placeholder="Rechercher un utilisateur..." fullWidth /><Select aria-label="Filtrer par rôle" value={roleFilter} onChange={(event) => setRoleFilter(event.target.value)} options={[{ value: 'all', label: 'Tous les rôles' }, ...Object.entries(ROLE_LABEL).map(([value, label]) => ({ value, label }))]} /><Select aria-label="Filtrer par état du compte" value={statusFilter} onChange={(event) => setStatusFilter(event.target.value as 'all' | UserStatus)} options={[{ value: 'all', label: 'Tous les états' }, { value: 'active', label: 'Actif' }, { value: 'inactive', label: 'Désactivé' }]} /></div></div>
            <DataTable columns={columns} data={filteredUsers} pageSize={6} initialSortKey="name" />
        </Card>
        <Modal isOpen={isFormOpen} onClose={() => setFormOpen(false)} title={editingUser ? 'Modifier le compte' : 'Créer un utilisateur'} size="medium" footer={<><Button variant="outline" onClick={() => setFormOpen(false)}>Annuler</Button><Button type="submit" form="user-form">{editingUser ? 'Enregistrer' : 'Créer le compte'}</Button></>}>
            <form id="user-form" className="sa-management-form" onSubmit={handleSubmit}>
                <p className="sa-management-form__hint">La création suit UserRequest ; l’association à l’organisation utilise ensuite OrganizationUserRequest. La maquette simule ces deux opérations.</p>
                <FormField label="Prénom" htmlFor="user-first-name" required><Input id="user-first-name" value={form.firstName} onChange={(event) => change('firstName', event.target.value)} maxLength={100} required fullWidth /></FormField>
                <FormField label="Nom" htmlFor="user-last-name" required><Input id="user-last-name" value={form.lastName} onChange={(event) => change('lastName', event.target.value)} maxLength={100} required fullWidth /></FormField>
                <FormField label="Adresse e-mail" htmlFor="user-email" required><Input id="user-email" type="email" value={form.email} onChange={(event) => change('email', event.target.value)} maxLength={180} required fullWidth /></FormField>
                <FormField label="Téléphone" htmlFor="user-phone"><Input id="user-phone" value={form.phone} onChange={(event) => change('phone', event.target.value)} maxLength={30} fullWidth /></FormField>
                {!editingUser && <FormField label="Mot de passe initial" htmlFor="user-password" required><Input id="user-password" type="password" minLength={8} maxLength={255} value={form.password} onChange={(event) => change('password', event.target.value)} autoComplete="new-password" required fullWidth /></FormField>}
                <FormField label="Rôle" htmlFor="user-role" required><Select id="user-role" value={form.role} onChange={(event) => change('role', event.target.value as UserRow['role'])} options={(['super_admin', 'patron', 'admin_immobilier', 'admin_ville'] as const).map((value) => ({ value, label: ROLE_LABEL[value] }))} fullWidth /></FormField>
                {form.role !== 'super_admin' && <FormField label="Organisation" htmlFor="user-organization" required><Select id="user-organization" value={form.organizationUuid} onChange={(event) => change('organizationUuid', event.target.value)} options={INITIAL_ORGANIZATIONS.map((organization) => ({ value: organization.id, label: organization.name }))} fullWidth /></FormField>}
            </form>
        </Modal>
        <ConfirmDialog isOpen={pendingAction !== null} onClose={() => setPendingAction(null)} onCancel={() => setPendingAction(null)} onConfirm={confirmPendingAction} title={pendingAction?.kind === 'delete' ? 'Supprimer ce compte ?' : pendingAction?.user.status === 'active' ? 'Désactiver ce compte ?' : 'Réactiver ce compte ?'} message={pendingAction?.kind === 'delete' ? 'La suppression est logique et conserve les traces historiques associées au compte.' : 'L’accès à la plateforme sera désactivé ou rétabli.'} confirmLabel={pendingAction?.kind === 'delete' ? 'Supprimer' : pendingAction?.user.status === 'active' ? 'Désactiver' : 'Réactiver'} />
    </div>;
}
