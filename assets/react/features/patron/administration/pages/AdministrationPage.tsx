import { useState, type FormEvent } from 'react';

import { OrganizationSummary } from '../../../shared/components/OrganizationSummary';
import { Card } from '../../../../components/UI/Card';
import { Tabs } from '../../../../components/Navigation/Tabs';
import { SearchInput } from '../../../../components/Forms/SearchInput';
import { Select } from '../../../../components/Forms/Select';
import { Input } from '../../../../components/Forms/Input';
import { FormField } from '../../../../components/Forms/FormField';
import { Textarea } from '../../../../components/Forms/Textarea';
import { Checkbox } from '../../../../components/Forms/Checkbox';
import { Button } from '../../../../components/UI/Button';
import { Modal } from '../../../../components/UI/Modal';
import { Loading } from '../../../../components/UI/Loading';
import { ErrorState } from '../../../../components/UI/ErrorState';
import { EmptyState } from '../../../../components/Data/EmptyState';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { useAdministration } from '../hooks/useAdministration';
import { TeamTable } from '../components/TeamTable';
import { OrganizationProfileCard } from '../components/OrganizationProfileCard';
import { RoleAssignmentsPanel } from '../components/RoleAssignmentsPanel';
import { CityAssignmentModal } from '../components/CityAssignmentModal';
import { fieldErrorMap } from '../../../shared/actionErrors';
import type { TeamMember } from '../types/administration.types';
import '../../../../../styles/pages/patron/administration/_administration.scss';

const ROLE_OPTIONS = [
    { value: 'all', label: 'Tous les rôles' },
    { value: 'patron', label: 'Patron' },
    { value: 'admin_immobilier', label: 'Admin immobilier' },
    { value: 'admin_ville', label: 'Admin ville' },
];

const INVITE_ROLE_OPTIONS = [
    { value: 'admin_immobilier', label: 'Admin immobilier' },
    { value: 'admin_ville', label: 'Admin ville' },
];

/**
 * Administration : entrée unique du PATRON, branchée sur l'API.
 *
 * - Organisation : coordonnées du tenant (`PUT /v1/identity/organizations/{uuid}`).
 * - Équipe : membres de l'organization active (`GET /v1/identity/users` filtré
 *   côté service sur `organizationUuid`).
 * - « Inviter un membre » crée un ADMIN_IMMOBILIER ou un ADMIN_VILLE (ce
 *   dernier avec au moins une ville) via `/organization-users/create-admin`.
 * - Actions : Modifier (fiche), Gérer les villes (ADMIN_VILLE,
 *   `/identity/user-cities`), Suspendre (désactivation + email) et Réactiver
 *   (`PUT /users/{uuid}` `isActive: true`).
 *   Pas de suppression : les comptes et leurs traces sont conservés.
 */
export function AdministrationPage() {
    const { currentOrganization } = useOrganization();
    const {
        members,
        cities,
        rows,
        loading,
        error,
        reload,
        search,
        setSearch,
        role,
        setRole,
        createMember,
        updateMember,
        suspendMember,
        reactivateMember,
    } = useAdministration();

    const [inviteOpen, setInviteOpen] = useState(false);
    const [inviteForm, setInviteForm] = useState<{ fullName: string; email: string; phone: string; role: 'admin_immobilier' | 'admin_ville'; cityUuids: string[] }>({ fullName: '', email: '', phone: '', role: 'admin_immobilier', cityUuids: [] });
    const [inviteErrors, setInviteErrors] = useState<Record<string, string>>({});
    const [creating, setCreating] = useState(false);

    const [editTarget, setEditTarget] = useState<TeamMember | null>(null);
    const [editForm, setEditForm] = useState({ name: '', phone: '' });
    const [editing, setEditing] = useState(false);

    const [suspendTarget, setSuspendTarget] = useState<TeamMember | null>(null);
    const [suspendReason, setSuspendReason] = useState('');
    const [suspending, setSuspending] = useState(false);

    const [citiesTarget, setCitiesTarget] = useState<TeamMember | null>(null);
    const [reactivating, setReactivating] = useState<string | null>(null);

    const closeInvite = () => {
        setInviteOpen(false);
        setInviteErrors({});
    };

    const toggleInviteCity = (cityUuid: string) => {
        setInviteForm((current) => ({
            ...current,
            cityUuids: current.cityUuids.includes(cityUuid)
                ? current.cityUuids.filter((id) => id !== cityUuid)
                : [...current.cityUuids, cityUuid],
        }));
    };

    const submitInvite = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (creating) return;
        setCreating(true);
        setInviteErrors({});
        try {
            await createMember({
                fullName: inviteForm.fullName.trim(),
                email: inviteForm.email.trim(),
                phone: inviteForm.phone.trim(),
                role: inviteForm.role,
                cityUuids: inviteForm.cityUuids,
            });
            closeInvite();
            setInviteForm({ fullName: '', email: '', phone: '', role: 'admin_immobilier', cityUuids: [] });
        } catch (cause) {
            const map = fieldErrorMap(cause);
            if (Object.keys(map).length > 0) setInviteErrors(map);
        } finally {
            setCreating(false);
        }
    };

    const openEdit = (member: TeamMember) => {
        setEditTarget(member);
        setEditForm({ name: member.name, phone: member.phone ?? '' });
    };

    const submitEdit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!editTarget || editing) return;
        setEditing(true);
        try {
            await updateMember(editTarget.id, { fullName: editForm.name.trim(), phone: editForm.phone.trim() });
            setEditTarget(null);
        } catch {
            // Le toast d'échec est déjà émis par le hook.
        } finally {
            setEditing(false);
        }
    };

    const openSuspend = (member: TeamMember) => {
        setSuspendTarget(member);
        setSuspendReason('');
    };

    const submitSuspend = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!suspendTarget || suspending) return;
        setSuspending(true);
        try {
            await suspendMember(suspendTarget.id, suspendReason.trim() || undefined);
            setSuspendTarget(null);
        } catch {
            // Le toast d'échec est déjà émis par le hook.
        } finally {
            setSuspending(false);
        }
    };

    const handleReactivate = async (member: TeamMember) => {
        if (reactivating) return;
        setReactivating(member.id);
        try {
            await reactivateMember(member.id);
        } catch {
            // Le toast d'échec est déjà émis par le hook.
        } finally {
            setReactivating(null);
        }
    };

    return (
        <main className="organization-page organization-operational-page">
            <header className="organization-page__header">
                <div>
                    <span className="organization-page__eyebrow">ACCÈS ET ÉQUIPE</span>
                    <h1>Administration</h1>
                    <p>Gérez l’organisation de {currentOrganization?.name ?? 'votre entreprise'} : coordonnées, invitation d’administrateurs, périmètres de villes et accès.</p>
                </div>
            </header>

            <Tabs
                className="patron-administration-tabs"
                variant="underline"
                tabs={[
                    { id: 'organization', label: 'Organisation' },
                    { id: 'team', label: `Équipe (${members.length})` },
                ]}
                renderContent={(activeTabId) => activeTabId === 'organization' ? (
                    <OrganizationProfileCard />
                ) : (
                    <>
                        <RoleAssignmentsPanel />
                        <OrganizationSummary items={[
                            { label: 'Membres de l’équipe', value: <>{members.length}</> },
                            { label: 'Administrateurs ville', value: <>{members.filter((member) => member.role === 'admin_ville').length}</> },
                            { label: 'Comptes désactivés', value: <>{members.filter((member) => member.status === 'inactive').length}</> },
                        ]} />

                        <Card className="organization-table-card" padding="medium">
                <div className="organization-table-toolbar">
                    <div>
                        <h2>Équipe de l’organisation</h2>
                        <p>Invitez des administrateurs immobilier ou ville (avec leurs villes), puis modifiez, suspendez ou réactivez les accès.</p>
                    </div>
                    <div className="organization-filters organization-filters--tenant">
                        <SearchInput value={search} onSearch={setSearch} placeholder="Rechercher un membre…" fullWidth />
                        <Select aria-label="Filtrer par rôle" value={role} onChange={(event) => setRole(event.target.value)} options={ROLE_OPTIONS} />
                        <Button onClick={() => setInviteOpen(true)}>＋ Inviter un membre</Button>
                    </div>
                </div>

                {loading && members.length === 0 ? (
                    <Loading text="Chargement des membres…" />
                ) : error && members.length === 0 ? (
                    <ErrorState title="Impossible de charger l’équipe" message={error} onRetry={() => void reload()} />
                ) : rows.length > 0 ? (
                    <TeamTable
                        rows={rows}
                        onEdit={openEdit}
                        onSuspend={openSuspend}
                        onReactivate={(member) => void handleReactivate(member)}
                        onManageCities={setCitiesTarget}
                    />
                ) : (
                    <EmptyState title="Aucun membre" description="Aucun membre ne correspond à cette recherche ou à ces filtres." />
                )}
                        </Card>
                    </>
                )}
            />

            <Modal isOpen={inviteOpen} onClose={closeInvite} title="Inviter un membre" size="medium" footer={<><Button variant="outline" onClick={closeInvite}>Annuler</Button><Button type="submit" form="invite-member-form" isLoading={creating}>{creating ? 'Invitation…' : 'Inviter le membre'}</Button></>}>
                <form id="invite-member-form" className="organization-management-form" onSubmit={submitInvite}>
                    <p className="organization-management-form__hint">Un email de configuration de mot de passe sera envoyé au membre invité.</p>
                    <FormField label="Rôle" htmlFor="invite-role" required error={inviteErrors.role}>
                        <Select id="invite-role" value={inviteForm.role} onChange={(event) => setInviteForm((current) => ({ ...current, role: event.target.value as 'admin_immobilier' | 'admin_ville' }))} options={INVITE_ROLE_OPTIONS} />
                    </FormField>
                    <FormField label="Nom complet" htmlFor="invite-full-name" required error={inviteErrors.fullName}>
                        <Input id="invite-full-name" value={inviteForm.fullName} onChange={(event) => setInviteForm((current) => ({ ...current, fullName: event.target.value }))} placeholder="Ex. : Jean Dupont" required fullWidth maxLength={200} />
                    </FormField>
                    <FormField label="Adresse e-mail" htmlFor="invite-email" required helpText="Ce sera son identifiant de connexion." error={inviteErrors.email}>
                        <Input id="invite-email" type="email" value={inviteForm.email} onChange={(event) => setInviteForm((current) => ({ ...current, email: event.target.value }))} placeholder="admin@immo-rdc.cd" required fullWidth maxLength={180} />
                    </FormField>
                    <FormField label="Téléphone" htmlFor="invite-phone" required error={inviteErrors.phone}>
                        <Input id="invite-phone" value={inviteForm.phone} onChange={(event) => setInviteForm((current) => ({ ...current, phone: event.target.value }))} placeholder="+243…" required fullWidth maxLength={30} />
                    </FormField>
                    {inviteForm.role === 'admin_ville' && (
                        <FormField label="Villes attribuées" error={inviteErrors.cityUuids} helpText="Sélectionnez une ou plusieurs villes. L’administrateur pourra basculer entre les villes attribuées.">
                            <div className="patron-city-picker">
                                {cities.length === 0 ? (
                                    <p className="patron-city-picker__empty">Aucune ville dans l’organisation.</p>
                                ) : cities.map((city) => (
                                    <Checkbox
                                        key={city.uuid}
                                        label={city.name}
                                        checked={inviteForm.cityUuids.includes(city.uuid)}
                                        onChange={() => toggleInviteCity(city.uuid)}
                                    />
                                ))}
                            </div>
                            {inviteForm.cityUuids.length > 0 && <small>{inviteForm.cityUuids.length} ville{inviteForm.cityUuids.length > 1 ? 's' : ''} sélectionnée{inviteForm.cityUuids.length > 1 ? 's' : ''}</small>}
                        </FormField>
                    )}
                </form>
            </Modal>

            <Modal isOpen={editTarget !== null} onClose={() => setEditTarget(null)} title={editTarget ? `Modifier « ${editTarget.name} »` : 'Modifier le membre'} size="medium" footer={<><Button variant="outline" onClick={() => setEditTarget(null)}>Annuler</Button><Button type="submit" form="edit-member-form" isLoading={editing}>{editing ? 'Enregistrement…' : 'Enregistrer'}</Button></>}>
                <form id="edit-member-form" className="organization-management-form" onSubmit={submitEdit}>
                    <p className="organization-management-form__hint">Seuls le nom et le téléphone sont modifiables ici ; le rôle et l’accès d’organisation ne changent pas.</p>
                    <FormField label="Nom complet" htmlFor="edit-full-name" required>
                        <Input id="edit-full-name" value={editForm.name} onChange={(event) => setEditForm((current) => ({ ...current, name: event.target.value }))} required fullWidth maxLength={200} />
                    </FormField>
                    <FormField label="Téléphone" htmlFor="edit-phone" required>
                        <Input id="edit-phone" value={editForm.phone} onChange={(event) => setEditForm((current) => ({ ...current, phone: event.target.value }))} required fullWidth maxLength={30} />
                    </FormField>
                </form>
            </Modal>

            <Modal isOpen={suspendTarget !== null} onClose={() => setSuspendTarget(null)} title={suspendTarget ? `Suspendre « ${suspendTarget.name} »` : 'Suspendre le membre'} size="medium" footer={<><Button variant="outline" onClick={() => setSuspendTarget(null)}>Annuler</Button><Button type="submit" form="suspend-member-form" variant="danger" isLoading={suspending}>{suspending ? 'Suspension…' : 'Suspendre le compte'}</Button></>}>
                <form id="suspend-member-form" className="organization-management-form" onSubmit={submitSuspend}>
                    <p className="organization-management-form__hint">Le compte est désactivé immédiatement et {suspendTarget ? ` ${suspendTarget.email} ` : 'le membre '}reçoit un email de notification. Le motif est facultatif et archivé dans le journal d’audit.</p>
                    <FormField label="Motif de la suspension" htmlFor="suspend-reason" helpText="Facultatif. Visible par l’utilisateur dans l’email de notification.">
                        <Textarea id="suspend-reason" rows={4} maxLength={1000} placeholder="Ex. : Compte dormant depuis plusieurs mois." value={suspendReason} onChange={(event) => setSuspendReason(event.target.value)} fullWidth />
                    </FormField>
                </form>
            </Modal>

            <CityAssignmentModal member={citiesTarget} onClose={() => setCitiesTarget(null)} />
        </main>
    );
}
