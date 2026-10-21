import { useState, type FormEvent } from 'react';

import { OrganizationSummary } from '../../../shared/components/OrganizationSummary';
import { Card } from '../../../../components/UI/Card';
import { Tabs } from '../../../../components/Navigation/Tabs';
import { SearchInput } from '../../../../components/Forms/SearchInput';
import { Select } from '../../../../components/Forms/Select';
import { Input } from '../../../../components/Forms/Input';
import { FormField } from '../../../../components/Forms/FormField';
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
import { fieldErrorMap } from '../../../shared/utils/actionErrors';
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
 * - Organisation : coordonnées consultées via `GET /v1/identity/organizations/{uuid}`.
 * - Équipe : affectations consultées via l’endpoint dédié autorisé à VIEW_USER.
 * - « Inviter un membre » crée un ADMIN_IMMOBILIER ou un ADMIN_VILLE (ce
 *   dernier avec au moins une ville) via `/organization-users/create-admin`.
 * - Les champs d’organisation et les membres sont en lecture seule ; le
 *   PATRON ne reçoit pas MANAGE_USERS ni MANAGE_ORGANIZATION.
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
    } = useAdministration();

    const [inviteOpen, setInviteOpen] = useState(false);
    const [inviteForm, setInviteForm] = useState<{ fullName: string; email: string; phone: string; role: 'admin_immobilier' | 'admin_ville'; cityUuids: string[] }>({ fullName: '', email: '', phone: '', role: 'admin_immobilier', cityUuids: [] });
    const [inviteErrors, setInviteErrors] = useState<Record<string, string>>({});
    const [creating, setCreating] = useState(false);

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

    return (
        <main className="organization-page organization-operational-page">
            <header className="organization-page__header">
                <div>
                    <span className="organization-page__eyebrow">ACCÈS ET ÉQUIPE</span>
                    <h1>Administration</h1>
                    <p>Consultez l’organisation de {currentOrganization?.name ?? 'votre entreprise'} et l’équipe. Vous pouvez inviter les rôles qui vous sont autorisés.</p>
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
                        <OrganizationSummary items={[
                            { label: 'Membres de l’équipe', value: <>{members.length}</> },
                            { label: 'Administrateurs ville', value: <>{members.filter((member) => member.role === 'admin_ville').length}</> },
                            { label: 'Comptes désactivés', value: <>{members.filter((member) => member.status === 'inactive').length}</> },
                        ]} />

                        <Card className="organization-table-card" padding="medium">
                <div className="organization-table-toolbar">
                    <div>
                        <h2>Équipe de l’organisation</h2>
                        <p>Consultez les membres et leurs périmètres. Vous pouvez inviter un nouvel administrateur ; la modification des accès est réservée aux rôles autorisés.</p>
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
                    <TeamTable rows={rows} />
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

        </main>
    );
}
