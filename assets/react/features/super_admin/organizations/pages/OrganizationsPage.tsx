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
import { PlatformPageHeader } from '../../shared/PlatformPageHeader';
import { RowActions } from '../../shared/RowActions';
import { OrganizationsStats } from '../components/OrganizationsStats';
import type { OrganizationStatus } from '../../dashbord/types';
import type { OrganizationStatusFilter, OrganizationRow } from '../types/organization.types';
import { useOrganizations } from '../hooks/useOrganizations';
import '../../../../../styles/pages/super_admin/organizations/_organizations.scss';

const STATUS_LABEL: Record<OrganizationStatus, string> = { active: 'Active', suspended: 'Suspendue', inactive: 'Inactive' };
const STATUS_VARIANT: Record<OrganizationStatus, 'success' | 'error' | 'secondary'> = { active: 'success', suspended: 'error', inactive: 'secondary' };
type OrganizationForm = { name: string; code: string; email: string; phone: string; address: string; city: string; country: string; patronFullName: string; patronEmail: string; patronPhone: string };
const EMPTY_FORM: OrganizationForm = { name: '', code: '', email: '', phone: '', address: '', city: '', country: 'RDC', patronFullName: '', patronEmail: '', patronPhone: '' };

export function OrganizationsPage() {
    const { organizations, filteredOrganizations, search, setSearch, statusFilter, setStatusFilter, addOrganization, updateOrganization, deleteOrganization } = useOrganizations();
    const [isFormOpen, setFormOpen] = useState(false);
    const [editingOrganization, setEditingOrganization] = useState<OrganizationRow | null>(null);
    const [form, setForm] = useState(EMPTY_FORM);
    const [pendingAction, setPendingAction] = useState<{ organization: OrganizationRow; kind: 'toggle' | 'delete' } | null>(null);

    const openCreate = () => { setEditingOrganization(null); setForm(EMPTY_FORM); setFormOpen(true); };
    const openEdit = (organization: OrganizationRow) => {
        setEditingOrganization(organization);
        setForm({ ...EMPTY_FORM, ...organization });
        setFormOpen(true);
    };
    const change = (key: keyof OrganizationForm, value: string) => setForm((current) => ({ ...current, [key]: value }));

    const columns: DataTableColumn<OrganizationRow>[] = [
        { key: 'name', title: 'Organisation', sortable: true, render: (organization) => <div className="sa-management-identity"><span className="sa-table__logo">{organization.name.charAt(0)}</span><span><strong>{organization.name}</strong><small>{organization.code}</small></span></div> },
        { key: 'city', title: 'Ville du siège', sortable: true },
        { key: 'email', title: 'Contact', sortable: true },
        { key: 'status', title: 'Statut', sortable: true, render: (organization) => <Badge variant={STATUS_VARIANT[organization.status]}>{STATUS_LABEL[organization.status]}</Badge> },
        { key: 'createdAt', title: 'Créée le', sortable: true, render: (organization) => new Date(organization.createdAt).toLocaleDateString('fr-FR') },
        { key: 'actions', title: 'Actions', render: (organization) => <RowActions label={organization.name} isActive={organization.status === 'active'} onEdit={() => openEdit(organization)} onToggleActive={() => setPendingAction({ organization, kind: 'toggle' })} onDelete={() => setPendingAction({ organization, kind: 'delete' })} /> },
    ];

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (editingOrganization) {
            updateOrganization(editingOrganization.id, { name: form.name.trim(), code: form.code.trim(), email: form.email.trim(), phone: form.phone.trim(), address: form.address.trim(), city: form.city, status: editingOrganization.status });
        } else {
            addOrganization({ name: form.name.trim(), code: form.code.trim(), email: form.email.trim(), phone: form.phone.trim(), address: form.address.trim(), city: form.city, status: 'active' });
        }
        setFormOpen(false);
    };

    const confirmPendingAction = () => {
        if (!pendingAction) return;
        const { organization } = pendingAction;
        if (pendingAction.kind === 'delete') deleteOrganization(organization.id);
        else updateOrganization(organization.id, { status: organization.status === 'active' ? 'suspended' : 'active' });
        setPendingAction(null);
    };

    return <div className="sa-dashboard sa-management-page">
        <PlatformPageHeader title="Organisations" description="Créez, modifiez et gérez l’accès des organisations à la plateforme." icon="briefcase" action={<Button icon={<span aria-hidden="true">＋</span>} onClick={openCreate}>Nouvelle organisation</Button>} />
        <OrganizationsStats organizations={organizations} />
        <Card className="sa-management-table-card" padding="medium">
            <div className="sa-management-toolbar"><div><h2>Liste des organisations</h2><p>Les actions sont simulées dans la maquette et reprennent les opérations disponibles dans l’API.</p></div><div className="sa-management-filters"><SearchInput value={search} onSearch={setSearch} placeholder="Rechercher une organisation..." fullWidth /><Select aria-label="Filtrer par statut" value={statusFilter} onChange={(event) => setStatusFilter(event.target.value as OrganizationStatusFilter)} options={[{ value: 'all', label: 'Tous les statuts' }, { value: 'active', label: 'Active' }, { value: 'suspended', label: 'Suspendue' }, { value: 'inactive', label: 'Inactive' }]} /></div></div>
            <DataTable columns={columns} data={filteredOrganizations} pageSize={12} initialSortKey="name" />
        </Card>
        <Modal isOpen={isFormOpen} onClose={() => setFormOpen(false)} title={editingOrganization ? 'Modifier l’organisation' : 'Créer une organisation'} size="medium" footer={<><Button variant="outline" onClick={() => setFormOpen(false)}>Annuler</Button><Button type="submit" form="organization-form">{editingOrganization ? 'Enregistrer' : 'Créer l’organisation'}</Button></>}>
            <form id="organization-form" className="sa-management-form" onSubmit={handleSubmit}>
                <p className="sa-management-form__hint">Les champs suivent OrganizationRequest. La création inclut les coordonnées du patron initial.</p>
                <FormField label="Nom de l’organisation" htmlFor="organization-name" required><Input id="organization-name" value={form.name} onChange={(event) => change('name', event.target.value)} maxLength={150} required fullWidth /></FormField>
                <FormField label="Code unique" htmlFor="organization-code" required><Input id="organization-code" value={form.code} onChange={(event) => change('code', event.target.value.toUpperCase())} maxLength={30} required fullWidth /></FormField>
                <FormField label="E-mail de contact" htmlFor="organization-email" required><Input id="organization-email" type="email" value={form.email} onChange={(event) => change('email', event.target.value)} maxLength={180} required fullWidth /></FormField>
                <FormField label="Téléphone" htmlFor="organization-phone" required><Input id="organization-phone" value={form.phone} onChange={(event) => change('phone', event.target.value)} maxLength={30} required fullWidth /></FormField>
                <FormField label="Ville du siège" htmlFor="organization-city" required><Input id="organization-city" value={form.city} onChange={(event) => change('city', event.target.value)} maxLength={100} required fullWidth /></FormField>
                <FormField label="Adresse" htmlFor="organization-address"><Input id="organization-address" value={form.address} onChange={(event) => change('address', event.target.value)} maxLength={255} fullWidth /></FormField>
                {!editingOrganization && <>
                    <FormField label="Nom complet du patron" htmlFor="patron-name" required><Input id="patron-name" value={form.patronFullName} onChange={(event) => change('patronFullName', event.target.value)} maxLength={200} required fullWidth /></FormField>
                    <FormField label="E-mail du patron" htmlFor="patron-email" required><Input id="patron-email" type="email" value={form.patronEmail} onChange={(event) => change('patronEmail', event.target.value)} maxLength={180} required fullWidth /></FormField>
                    <FormField label="Téléphone du patron" htmlFor="patron-phone" required><Input id="patron-phone" value={form.patronPhone} onChange={(event) => change('patronPhone', event.target.value)} maxLength={30} required fullWidth /></FormField>
                </>}
            </form>
        </Modal>
        <ConfirmDialog isOpen={pendingAction !== null} onClose={() => setPendingAction(null)} onCancel={() => setPendingAction(null)} onConfirm={confirmPendingAction} title={pendingAction?.kind === 'delete' ? 'Supprimer cette organisation ?' : pendingAction?.organization.status === 'active' ? 'Suspendre cette organisation ?' : 'Réactiver cette organisation ?'} message={pendingAction?.kind === 'delete' ? 'La suppression est logique : les données historiques sont conservées et l’accès de l’organisation est désactivé.' : 'Le statut de l’organisation sera modifié. Cette action pourra être inversée depuis le menu Actions.'} confirmLabel={pendingAction?.kind === 'delete' ? 'Supprimer' : pendingAction?.organization.status === 'active' ? 'Suspendre' : 'Réactiver'} />
    </div>;
}
