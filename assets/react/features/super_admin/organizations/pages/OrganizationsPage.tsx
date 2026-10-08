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
import { Input } from '../../../../components/Forms/Input';
import { PlatformPageHeader } from '../../shared/PlatformPageHeader';
import { RowActions } from '../../shared/RowActions';
import { OrganizationsStats } from '../components/OrganizationsStats';
import { OrganizationLogoPicker, type OrganizationLogoChange } from '../components/OrganizationLogoPicker';
import { OrganizationWizardModal } from '../components/OrganizationWizardModal';
import type { OrganizationStatus } from '../../dashbord/types';
import type { OrganizationStatusFilter, OrganizationRow } from '../types/organization.types';
import { useOrganizations } from '../hooks/useOrganizations';
import { logoHref } from '../services/organizationsService';
import { formatUserDate } from '../../../../services/userPreferences';
import '../../../../../styles/pages/super_admin/organizations/_organizations.scss';

const STATUS_LABEL: Record<OrganizationStatus, string> = { active: 'Active', suspended: 'Suspendue', inactive: 'Inactive' };
const STATUS_VARIANT: Record<OrganizationStatus, 'success' | 'error' | 'secondary'> = { active: 'success', suspended: 'error', inactive: 'secondary' };
type OrganizationForm = { name: string; code: string; email: string; phone: string; address: string; city: string };
const EMPTY_FORM: OrganizationForm = { name: '', code: '', email: '', phone: '', address: '', city: '' };

/**
 * Logo ou initiale d'une organisation dans la table.
 * Affiche l'image du logo quand il est joignable ; si l'URL renvoyée par
 * l'API est indisponible (ex. valeur placeholder `cdn.example.com`), on
 * retombe sur l'initiale plutôt que sur une image cassée.
 */
function OrganizationLogoCell({ organization }: { organization: OrganizationRow }) {
    const [failed, setFailed] = useState(false);
    const src = organization.logo ? logoHref(organization.logo) : null;

    if (!src || failed) {
        return <span className="sa-table__logo">{organization.name.charAt(0)}</span>;
    }

    return (
        <img
            className="sa-table__logo sa-table__logo--img"
            src={src}
            alt={`Logo de ${organization.name}`}
            onError={() => setFailed(true)}
        />
    );
}

export function OrganizationsPage() {
    const {
        organizations,
        filteredOrganizations,
        loading,
        error,
        reload,
        search,
        setSearch,
        statusFilter,
        setStatusFilter,
        addOrganization,
        updateOrganization,
        suspendOrganization,
        activateOrganization,
    } = useOrganizations();

const [isWizardOpen, setWizardOpen] = useState(false);
    const [isEditOpen, setEditOpen] = useState(false);
    const [editStep, setEditStep] = useState<1 | 2>(1);
    const [editingOrganization, setEditingOrganization] = useState<OrganizationRow | null>(null);
    const [editLogoChange, setEditLogoChange] = useState<OrganizationLogoChange>(null);
    const [form, setForm] = useState(EMPTY_FORM);
    const [suspendTarget, setSuspendTarget] = useState<OrganizationRow | null>(null);
    const [suspendReason, setSuspendReason] = useState('');
    const [suspending, setSuspending] = useState(false);

    const openEdit = (organization: OrganizationRow) => {
        setEditingOrganization(organization);
        setForm({ ...EMPTY_FORM, ...organization });
        setEditLogoChange(null);
        setEditStep(1);
        setEditOpen(true);
    };
    const change = (key: keyof OrganizationForm, value: string) => setForm((current) => ({ ...current, [key]: value }));

    const openSuspend = (organization: OrganizationRow) => {
        setSuspendTarget(organization);
        setSuspendReason('');
    };

    const columns: DataTableColumn<OrganizationRow>[] = [
        { key: 'name', title: 'Organisation', sortable: true, render: (organization) => <div className="sa-management-identity"><OrganizationLogoCell organization={organization} /><span><strong>{organization.name}</strong><small>{organization.code}</small></span></div> },
        { key: 'city', title: 'Ville du siège', sortable: true },
        { key: 'email', title: 'Contact', sortable: true },
        { key: 'status', title: 'Statut', sortable: true, render: (organization) => <Badge variant={STATUS_VARIANT[organization.status]}>{STATUS_LABEL[organization.status]}</Badge> },
        { key: 'createdAt', title: 'Créée le', sortable: true, render: (organization) => formatUserDate(organization.createdAt) },
        { key: 'actions', title: 'Actions', render: (organization) => <RowActions label={organization.name} isActive={organization.status === 'active'} onEdit={() => openEdit(organization)} onToggleActive={() => { if (organization.status === 'active') openSuspend(organization); else void reactivateOrganization(organization); }} /> },
    ];

    const handleCreate = (payload: Parameters<typeof addOrganization>[0]) => addOrganization(payload);

    const handleEditSubmit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!editingOrganization) return;

        if (editStep === 1) {
            setEditStep(2);
            return;
        }

        try {
            const payload: Parameters<typeof updateOrganization>[1] = {
                name: form.name.trim(),
                code: form.code.trim(),
                email: form.email.trim(),
                phone: form.phone.trim(),
                address: form.address.trim(),
                city: form.city,
                status: editingOrganization.status,
                logoFile: editLogoChange?.kind === 'new' ? editLogoChange.file : undefined,
                removeLogo: editLogoChange?.kind === 'removed' ? true : undefined,
            };
            await updateOrganization(editingOrganization.id, payload);
            setEditOpen(false);
        } catch {
            // Le toast d'échec est déjà émis par le hook.
        }
    };

    const submitSuspend = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!suspendTarget || suspending) return;
        setSuspending(true);
        try {
            await suspendOrganization(suspendTarget.id, suspendReason.trim());
            setSuspendTarget(null);
        } catch {
            // Le toast d'échec est déjà émis par le hook.
        } finally {
            setSuspending(false);
        }
    };

    const reactivateOrganization = async (organization: OrganizationRow) => {
        try {
            await activateOrganization(organization.id);
        } catch {
            // Le toast d'échec est déjà émis par le hook.
        }
    };

    return <div className="sa-dashboard sa-management-page">
        <PlatformPageHeader title="Organisations" description="Créez, modifiez et gérez l’accès des organisations à la plateforme." icon="briefcase" action={<Button icon={<span aria-hidden="true">＋</span>} onClick={() => setWizardOpen(true)}>Nouvelle organisation</Button>} />
        {loading && organizations.length === 0 ? (
            <Card className="sa-management-table-card" padding="medium"><Loading text="Chargement des organisations…" /></Card>
        ) : error && organizations.length === 0 ? (
            <Card className="sa-management-table-card" padding="medium"><ErrorState title="Impossible de charger les organisations" message={error} onRetry={() => void reload()} /></Card>
        ) : (
            <>
                <OrganizationsStats organizations={organizations} />
                <Card className="sa-management-table-card" padding="medium">
                    <div className="sa-management-toolbar"><div><h2>Liste des organisations</h2><p>Les données sont lues et écrites via l’API : les actions créent, modifient, suspendent ou réactivent réellement les organisations.</p></div><div className="sa-management-filters"><SearchInput value={search} onSearch={setSearch} placeholder="Rechercher une organisation..." fullWidth /><Select aria-label="Filtrer par statut" value={statusFilter} onChange={(event) => setStatusFilter(event.target.value as OrganizationStatusFilter)} options={[{ value: 'all', label: 'Tous les statuts' }, { value: 'active', label: 'Active' }, { value: 'suspended', label: 'Suspendue' }, { value: 'inactive', label: 'Inactive' }]} /></div></div>
                    <DataTable columns={columns} data={filteredOrganizations} pageSize={12} initialSortKey="name" />
                </Card>
            </>
        )}
        <OrganizationWizardModal isOpen={isWizardOpen} onClose={() => setWizardOpen(false)} onCreate={handleCreate} />
        <Modal isOpen={suspendTarget !== null} onClose={() => setSuspendTarget(null)} title={suspendTarget ? `Suspendre « ${suspendTarget.name} »` : 'Suspendre l’organisation'} size="medium" footer={<><Button variant="outline" onClick={() => setSuspendTarget(null)}>Annuler</Button><Button type="submit" form="suspend-organization-form" isLoading={suspending}>{suspending ? 'Suspension…' : 'Suspendre l’organisation'}</Button></>}>
            <form id="suspend-organization-form" className="sa-management-form" onSubmit={submitSuspend}>
                <p className="sa-management-form__hint">La suspension désactive l’accès de tous les membres de l’organisation. Le motif est obligatoire : il sera archivé dans le journal d’audit et envoyé par email à chaque utilisateur de cette organisation.</p>
                <FormField label="Motif de la suspension" htmlFor="suspend-organization-reason" required helpText="Ce motif sera visible par les utilisateurs de l’organisation dans l’email de notification.">
                    <Textarea id="suspend-organization-reason" rows={4} maxLength={1000} placeholder="Ex. : Suspension pour non-paiement des frais d’abonnement." value={suspendReason} onChange={(event) => setSuspendReason(event.target.value)} required fullWidth />
                </FormField>
            </form>
        </Modal>
        <Modal isOpen={isEditOpen} onClose={() => setEditOpen(false)} title="Modifier l’organisation" size="medium" footer={editStep === 1 ? <><Button variant="outline" onClick={() => setEditOpen(false)}>Annuler</Button><Button type="submit" form="organization-form">Continuer</Button></> : <><Button variant="outline" onClick={() => setEditStep(1)}>Précédent</Button><Button type="submit" form="organization-form">Enregistrer</Button></>}>
            {editStep === 1 ? (
                <form id="organization-form" className="sa-management-form" onSubmit={handleEditSubmit}>
                    <FormField label="Nom de l’organisation" htmlFor="organization-name" required><Input id="organization-name" placeholder="Ex. : Synerque Immobilier" value={form.name} onChange={(event) => change('name', event.target.value)} maxLength={150} required fullWidth /></FormField>
                    <FormField label="Code unique" htmlFor="organization-code"><Input id="organization-code" placeholder="Ex. : SYNERQUE" value={form.code} onChange={(event) => change('code', event.target.value.toUpperCase())} maxLength={30} fullWidth /></FormField>
                    <FormField label="E-mail de contact" htmlFor="organization-email" required><Input id="organization-email" type="email" placeholder="exemple@entreprise.com" value={form.email} onChange={(event) => change('email', event.target.value)} maxLength={180} required fullWidth /></FormField>
                    <FormField label="Téléphone" htmlFor="organization-phone" required><Input id="organization-phone" placeholder="+243 000 000 000" value={form.phone} onChange={(event) => change('phone', event.target.value)} maxLength={30} required fullWidth /></FormField>
                    <FormField label="Ville du siège" htmlFor="organization-city"><Input id="organization-city" placeholder="Ex. : Kinshasa" value={form.city} onChange={(event) => change('city', event.target.value)} maxLength={100} fullWidth /></FormField>
                    <FormField label="Adresse" htmlFor="organization-address"><Input id="organization-address" placeholder="Ex. : Avenue de la Paix, n°12" value={form.address} onChange={(event) => change('address', event.target.value)} maxLength={255} fullWidth /></FormField>
                </form>
            ) : (
                <form id="organization-form" className="sa-management-form" onSubmit={handleEditSubmit}>
                    <p className="sa-management-form__hint">Facultatif. Le logo est retaillé puis envoyé après l’enregistrement ; il apparaîtra dans la liste des organisations.</p>
                    <OrganizationLogoPicker value={editingOrganization?.logo ?? null} change={editLogoChange} onChange={setEditLogoChange} />
                </form>
            )}
        </Modal>
    </div>;
}
