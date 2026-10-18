import { useState, type FormEvent } from 'react';
import { useNavigate } from 'react-router-dom';
import { DataTable } from '../../../../components/Data/DataTable';
import type { DataTableColumn } from '../../../../hook-components/Data/DataTable';
import { FormField } from '../../../../components/Forms/FormField';
import { Input } from '../../../../components/Forms/Input';
import { SearchInput } from '../../../../components/Forms/SearchInput';
import { Select } from '../../../../components/Forms/Select';
import { Textarea } from '../../../../components/Forms/Textarea';
import { Alert } from '../../../../components/UI/Alert';
import { Badge } from '../../../../components/UI/Badge';
import { Button } from '../../../../components/UI/Button';
import { Card } from '../../../../components/UI/Card';
import { ErrorState } from '../../../../components/UI/ErrorState';
import { Loading } from '../../../../components/UI/Loading';
import { Modal } from '../../../../components/UI/Modal';
import { PlatformPageHeader } from '../../components/PlatformPageHeader';
import { RowActions } from '../../components/RowActions';
import { OrganizationsStats } from '../components/OrganizationsStats';
import type { OrganizationRow, OrganizationStatus, OrganizationStatusFilter } from '../types/organization.types';
import { useOrganizations } from '../hooks/useOrganizations';
import { logoHref } from '../services/organizationsService';
import { formatUserDate } from '../../../../services/userPreferences';
import '../../../../../styles/pages/super_admin/organizations/_organizations.scss';

const STATUS_LABEL: Record<OrganizationStatus, string> = { active: 'Active', suspended: 'Suspendue', inactive: 'Inactive' };
const STATUS_VARIANT: Record<OrganizationStatus, 'success' | 'error' | 'secondary'> = { active: 'success', suspended: 'error', inactive: 'secondary' };
const toLocalDateTime = (date: Date) => new Date(date.getTime() - date.getTimezoneOffset() * 60_000).toISOString().slice(0, 16);

function OrganizationLogoCell({ organization }: { organization: OrganizationRow }) {
    const [failed, setFailed] = useState(false);
    const src = organization.logo ? logoHref(organization.logo) : null;
    if (!src || failed) return <span className="sa-table__logo">{organization.name.charAt(0)}</span>;
    return <img className="sa-table__logo sa-table__logo--img" src={src} alt={`Logo de ${organization.name}`} onError={() => setFailed(true)} />;
}

export function OrganizationsPage() {
    const navigate = useNavigate();
    const { organizations, filteredOrganizations, loading, error, reload, search, setSearch, statusFilter, setStatusFilter, suspendOrganization, activateOrganization } = useOrganizations();
    const [suspendTarget, setSuspendTarget] = useState<OrganizationRow | null>(null);
    const [suspendReason, setSuspendReason] = useState('');
    const [suspendDuration, setSuspendDuration] = useState('indefinite');
    const [suspendUntil, setSuspendUntil] = useState('');
    const [suspending, setSuspending] = useState(false);

    const openSuspend = (organization: OrganizationRow) => {
        setSuspendTarget(organization);
        setSuspendReason('');
        setSuspendDuration('indefinite');
        setSuspendUntil('');
    };

    const reactivateOrganization = async (organization: OrganizationRow) => {
        try { await activateOrganization(organization.id); } catch { /* Le hook affiche le toast d’erreur. */ }
    };

    const columns: DataTableColumn<OrganizationRow>[] = [
        { key: 'name', title: 'Organisation', sortable: true, render: (organization) => <div className="sa-management-identity"><OrganizationLogoCell organization={organization} /><span><strong>{organization.name}</strong><small>{organization.code}</small></span></div> },
        { key: 'city', title: 'Ville du siège', sortable: true },
        { key: 'email', title: 'Contact', sortable: true },
        { key: 'showcaseEnabled', title: 'Vitrine', render: (organization) => <Badge variant={organization.showcaseEnabled ? 'success' : 'secondary'}>{organization.showcaseEnabled ? 'Autorisée' : 'Non autorisée'}</Badge> },
        { key: 'status', title: 'Statut', sortable: true, render: (organization) => <span><Badge variant={STATUS_VARIANT[organization.status]}>{STATUS_LABEL[organization.status]}</Badge>{organization.status === 'suspended' && organization.suspensionEndsAt && <small className="sa-management-status-detail">Jusqu’au {formatUserDate(organization.suspensionEndsAt, true)}</small>}</span> },
        { key: 'createdAt', title: 'Créée le', sortable: true, render: (organization) => formatUserDate(organization.createdAt) },
        { key: 'actions', title: 'Actions', render: (organization) => <RowActions label={organization.name} isActive={organization.status === 'active'} onEdit={() => navigate(`/app/admin/organisations/${organization.id}/modifier`)} onToggleActive={() => { if (organization.status === 'active') openSuspend(organization); else void reactivateOrganization(organization); }} /> },
    ];

    const submitSuspend = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!suspendTarget || suspending) return;
        setSuspending(true);
        try {
            const endsAt = suspendDuration === 'indefinite' ? null : suspendDuration === 'custom'
                ? new Date(suspendUntil).toISOString()
                : new Date(Date.now() + Number(suspendDuration) * 86_400_000).toISOString();
            await suspendOrganization(suspendTarget.id, suspendReason.trim(), endsAt);
            setSuspendTarget(null);
        } catch { /* Le hook affiche le toast d’erreur. */ }
        finally { setSuspending(false); }
    };

    return <div className="sa-dashboard sa-management-page">
        <PlatformPageHeader title="Organisations" description="Créez, modifiez et gérez l’accès des organisations à la plateforme." icon="briefcase" action={<Button icon={<span aria-hidden="true">＋</span>} onClick={() => navigate('/app/admin/organisations/nouvelle')}>Nouvelle organisation</Button>} />
        {loading && organizations.length === 0 ? <Card className="sa-management-table-card" padding="medium"><Loading text="Chargement des organisations…" /></Card>
            : error && organizations.length === 0 ? <Card className="sa-management-table-card" padding="medium"><ErrorState title="Impossible de charger les organisations" message={error} onRetry={() => void reload()} /></Card>
                : <>
                    <OrganizationsStats organizations={organizations} />
                    <Card className="sa-management-table-card" padding="medium">
                        <div className="sa-management-toolbar"><div><h2>Liste des organisations</h2><p>Gérez les organisations, leurs accès et leurs capacités.</p></div><div className="sa-management-filters"><SearchInput value={search} onSearch={setSearch} placeholder="Rechercher une organisation…" fullWidth /><Select aria-label="Filtrer par statut" value={statusFilter} onChange={(event) => setStatusFilter(event.target.value as OrganizationStatusFilter)} options={[{ value: 'all', label: 'Tous les statuts' }, { value: 'active', label: 'Active' }, { value: 'suspended', label: 'Suspendue' }, { value: 'inactive', label: 'Inactive' }]} /></div></div>
                        <DataTable columns={columns} data={filteredOrganizations} pageSize={12} initialSortKey="name" />
                    </Card>
                </>}

        <Modal isOpen={suspendTarget !== null} onClose={() => setSuspendTarget(null)} title={suspendTarget ? `Suspendre « ${suspendTarget.name} »` : 'Suspendre l’organisation'} size="medium" footer={<><Button variant="outline" onClick={() => setSuspendTarget(null)}>Annuler</Button><Button type="submit" form="suspend-organization-form" isLoading={suspending}>{suspending ? 'Suspension…' : 'Suspendre l’organisation'}</Button></>}>
            <form id="suspend-organization-form" className="sa-management-form" onSubmit={submitSuspend}>
                <p className="sa-management-form__hint">La suspension désactive l’accès de tous les membres. Le motif sera archivé dans le journal d’audit et envoyé par e-mail.</p>
                <FormField label="Durée" htmlFor="suspend-organization-duration" helpText="La suspension commence dès sa confirmation."><Select id="suspend-organization-duration" value={suspendDuration} onChange={(event) => setSuspendDuration(event.target.value)} options={[{ value: 'indefinite', label: 'Indéterminée — jusqu’à réactivation manuelle' }, { value: '15', label: '15 jours' }, { value: '30', label: '30 jours' }, { value: '90', label: '90 jours' }, { value: 'custom', label: 'Choisir une date et une heure de fin' }]} /></FormField>
                {suspendDuration === 'custom' && <FormField label="Fin de suspension" htmlFor="suspend-organization-until" required><Input id="suspend-organization-until" type="datetime-local" value={suspendUntil} onChange={(event) => setSuspendUntil(event.target.value)} min={toLocalDateTime(new Date(Date.now() + 60_000))} required /></FormField>}
                <FormField label="Motif de la suspension" htmlFor="suspend-organization-reason" required helpText="Le motif sera communiqué aux utilisateurs de l’organisation."><Textarea id="suspend-organization-reason" rows={4} maxLength={1000} placeholder="Ex. : Suspension pour non-paiement des frais d’abonnement." value={suspendReason} onChange={(event) => setSuspendReason(event.target.value)} required fullWidth /></FormField>
            </form>
        </Modal>
    </div>;
}
