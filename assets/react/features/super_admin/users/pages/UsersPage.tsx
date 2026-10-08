import { useState, type FormEvent } from 'react';
import { DataTable } from '../../../../components/Data/DataTable';
import type { DataTableColumn } from '../../../../hook-components/Data/DataTable';
import { FormField } from '../../../../components/Forms/FormField';
import { SearchInput } from '../../../../components/Forms/SearchInput';
import { Select } from '../../../../components/Forms/Select';
import { Textarea } from '../../../../components/Forms/Textarea';
import { Badge } from '../../../../components/UI/Badge';
import { Button } from '../../../../components/UI/Button';
import { Card } from '../../../../components/UI/Card';
import { ErrorState } from '../../../../components/UI/ErrorState';
import { Loading } from '../../../../components/UI/Loading';
import { Modal } from '../../../../components/UI/Modal';
import { Avatar } from '../../../../components/UI/Avatar';
import { PlatformPageHeader } from '../../shared/PlatformPageHeader';
import { UsersStats } from '../components/UsersStats';
import type { UserRow, UserRoleFilter, UserStatus } from '../types/user.types';
import { useUsers } from '../hooks/useUsers';
import { formatUserDate } from '../../../../services/userPreferences';
import { photoHref } from '../services/usersService';
import '../../../../../styles/pages/super_admin/users/_users.scss';

const ROLE_LABEL: Record<string, string> = { super_admin: 'Super admin', patron: 'Patron', admin_immobilier: 'Admin immobilier', admin_ville: 'Admin ville' };
const ROLE_VARIANT: Record<string, 'primary' | 'secondary' | 'info'> = { super_admin: 'primary', patron: 'secondary', admin_immobilier: 'info', admin_ville: 'info' };
const STATUS_LABEL: Record<UserStatus, string> = { active: 'Actif', inactive: 'Désactivé' };
const STATUS_VARIANT: Record<UserStatus, 'success' | 'error'> = { active: 'success', inactive: 'error' };

/**
 * Photo de profil d'un utilisateur dans la table.
 * Affiche l'image quand elle est joignable ; si l'URL renvoyée par l'API
 * est indisponible (ex. valeur placeholder), on retombe sur l'initiale
 * plutôt que sur une image cassée.
 */
function UserPhotoCell({ user }: { user: UserRow }) {
    const [failed, setFailed] = useState(false);
    const src = photoHref(user.profilePhoto);

    if (!src || failed) {
        return <Avatar name={user.name} size="small" />;
    }

    return (
        <img
            className="sa-table__logo sa-table__logo--img"
            src={src}
            alt={`Photo de ${user.name}`}
            onError={() => setFailed(true)}
        />
    );
}

export function UsersPage() {
    const {
        users,
        filteredUsers,
        organizations,
        loading,
        error,
        reload,
        search,
        setSearch,
        roleFilter,
        setRoleFilter,
        statusFilter,
        setStatusFilter,
        organizationFilter,
        setOrganizationFilter,
        suspendUser,
    } = useUsers();

    const [suspendTarget, setSuspendTarget] = useState<UserRow | null>(null);
    const [suspendReason, setSuspendReason] = useState('');
    const [suspending, setSuspending] = useState(false);

    const openSuspend = (user: UserRow) => {
        setSuspendTarget(user);
        setSuspendReason('');
    };

    const organizationFilterOptions = [
        { value: 'all', label: 'Toutes les organisations' },
        ...organizations.map((organization) => ({ value: organization.id, label: organization.name })),
        ...(users.some((user) => user.memberships.length === 0) ? [{ value: 'platform', label: 'Plateforme' }] : []),
    ];

    const columns: DataTableColumn<UserRow>[] = [
        { key: 'name', title: 'Utilisateur', sortable: true, render: (user) => <div className="sa-management-identity"><UserPhotoCell user={user} /><span><strong>{user.name}</strong><small>{user.email}</small></span></div> },
        { key: 'organization', title: 'Organisation', sortable: true },
        { key: 'role', title: 'Rôle organisationnel', sortable: true, render: (user) => user.role ? <Badge variant={ROLE_VARIANT[user.role] ?? 'secondary'}>{ROLE_LABEL[user.role] ?? user.role}</Badge> : '—' },
        { key: 'status', title: 'Compte', sortable: true, render: (user) => <Badge variant={STATUS_VARIANT[user.status]}>{STATUS_LABEL[user.status]}</Badge> },
        { key: 'lastLoginAt', title: 'Dernière connexion', sortable: true, render: (user) => user.lastLoginAt ? formatUserDate(user.lastLoginAt, true) : 'Jamais connecté' },
        { key: 'actions', title: 'Actions', render: (user) => (
            <Button
                variant="danger"
                size="small"
                disabled={user.status !== 'active'}
                title={user.status !== 'active' ? 'Le compte est déjà désactivé.' : undefined}
                onClick={() => openSuspend(user)}
            >
                Suspendre
            </Button>
        ) },
    ];

    const submitSuspend = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!suspendTarget || suspending) return;
        setSuspending(true);
        try {
            await suspendUser(suspendTarget.id, suspendReason.trim() || undefined);
            setSuspendTarget(null);
        } catch {
            // Le toast d'échec est déjà émis par le hook.
        } finally {
            setSuspending(false);
        }
    };

    return <div className="sa-dashboard sa-management-page">
        <PlatformPageHeader title="Utilisateurs" description="Consultez les comptes, leur appartenance aux organisations et suspendez ceux qui ne doivent plus accéder à la plateforme." icon="users" />
        {loading && users.length === 0 ? (
            <Card className="sa-management-table-card" padding="medium"><Loading text="Chargement des utilisateurs…" /></Card>
        ) : error && users.length === 0 ? (
            <Card className="sa-management-table-card" padding="medium"><ErrorState title="Impossible de charger les utilisateurs" message={error} onRetry={() => void reload()} /></Card>
        ) : (
            <>
                <UsersStats users={users} />
                <Card className="sa-management-table-card" padding="medium">
                    <div className="sa-management-toolbar"><div><h2>Comptes utilisateurs</h2><p>Les comptes sont lus depuis l’API ; suspendre un compte désactive réellement l’accès et notifie l’utilisateur par email.</p></div><div className="sa-management-filters sa-management-filters--users"><SearchInput value={search} onSearch={setSearch} placeholder="Rechercher un utilisateur..." fullWidth /><Select aria-label="Filtrer par organisation" value={organizationFilter} onChange={(event) => setOrganizationFilter(event.target.value)} options={organizationFilterOptions} /><Select aria-label="Filtrer par rôle" value={roleFilter} onChange={(event) => setRoleFilter(event.target.value as UserRoleFilter)} options={[{ value: 'all', label: 'Tous les rôles' }, ...Object.entries(ROLE_LABEL).map(([value, label]) => ({ value, label }))]} /><Select aria-label="Filtrer par état du compte" value={statusFilter} onChange={(event) => setStatusFilter(event.target.value as 'all' | UserStatus)} options={[{ value: 'all', label: 'Tous les états' }, { value: 'active', label: 'Actif' }, { value: 'inactive', label: 'Désactivé' }]} /></div></div>
                    <DataTable columns={columns} data={filteredUsers} pageSize={12} initialSortKey="name" />
                </Card>
            </>
        )}
        <Modal isOpen={suspendTarget !== null} onClose={() => setSuspendTarget(null)} title={suspendTarget ? `Suspendre « ${suspendTarget.name} »` : 'Suspendre le compte'} size="medium" footer={<><Button variant="outline" onClick={() => setSuspendTarget(null)}>Annuler</Button><Button type="submit" form="suspend-user-form" variant="danger" isLoading={suspending}>{suspending ? 'Suspension…' : 'Suspendre le compte'}</Button></>}>
            <form id="suspend-user-form" className="sa-management-form" onSubmit={submitSuspend}>
                <p className="sa-management-form__hint">Le compte est désactivé immédiatement et l’utilisateur reçoit un email de notification{suspendTarget ? ` à ${suspendTarget.email}` : ''}. Le motif est facultatif : joint à l’email, il est également archivé dans le journal d’audit.</p>
                <FormField label="Motif de la suspension" htmlFor="suspend-user-reason" helpText="Facultatif. Il sera visible par l’utilisateur dans l’email de notification.">
                    <Textarea id="suspend-user-reason" rows={4} maxLength={1000} placeholder="Ex. : Compte dormant depuis plusieurs mois." value={suspendReason} onChange={(event) => setSuspendReason(event.target.value)} fullWidth />
                </FormField>
            </form>
        </Modal>
    </div>;
}
