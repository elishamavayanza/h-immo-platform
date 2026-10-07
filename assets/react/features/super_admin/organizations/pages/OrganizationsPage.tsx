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
import { PlatformPageHeader } from '../../shared/PlatformPageHeader';
import { OrganizationsStats } from '../components/OrganizationsStats';
import type { OrganizationStatus } from '../../dashbord/types';
import type { OrganizationStatusFilter } from '../types/organization.types';
import { useOrganizations } from '../hooks/useOrganizations';
import '../../../../../styles/pages/super_admin/organizations/_organizations.scss';
import type { OrganizationRow } from '../types/organization.types';

const STATUS_LABEL: Record<OrganizationStatus, string> = { active: 'Active', trial: 'Essai', suspended: 'Suspendue' };
const STATUS_VARIANT: Record<OrganizationStatus, 'success' | 'info' | 'error'> = { active: 'success', trial: 'info', suspended: 'error' };
const ACTION_ICON = <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.5" /><circle cx="12" cy="12" r="1.5" /><circle cx="19" cy="12" r="1.5" /></svg>;

export function OrganizationsPage() {
    const { organizations, filteredOrganizations, search, setSearch, statusFilter, setStatusFilter, addOrganization } = useOrganizations();
    const [isCreateOpen, setCreateOpen] = useState(false);
    const [name, setName] = useState('');
    const [city, setCity] = useState('Kinshasa');
    const [plan, setPlan] = useState('Pro');

    const columns: DataTableColumn<OrganizationRow>[] = [
        { key: 'name', title: 'Organisation', sortable: true, render: (organization) => <div className="sa-management-identity"><span className="sa-table__logo">{organization.name.charAt(0)}</span><span><strong>{organization.name}</strong><small>{organization.code}</small></span></div> },
        { key: 'city', title: 'Ville', sortable: true },
        { key: 'plan', title: 'Offre', sortable: true, render: (organization) => <Badge variant="secondary">{organization.plan}</Badge> },
        { key: 'members', title: 'Utilisateurs', sortable: true },
        { key: 'properties', title: 'Biens', sortable: true },
        { key: 'status', title: 'Statut', sortable: true, render: (organization) => <Badge variant={STATUS_VARIANT[organization.status]}>{STATUS_LABEL[organization.status]}</Badge> },
        { key: 'createdAt', title: 'Créée le', sortable: true },
        { key: 'actions', title: '', render: (organization) => <IconButton variant="ghost" ariaLabel={`Actions pour ${organization.name}`} icon={ACTION_ICON} /> },
    ];

    const handleCreate = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!name.trim()) return;
        addOrganization({ name: name.trim(), code: 'NOU-ORG', city, plan, status: 'trial' });
        setName('');
        setCreateOpen(false);
    };

    return (
        <div className="sa-dashboard sa-management-page">
            <PlatformPageHeader
                title="Organisations"
                description="Gérez les organisations, leurs offres et leur accès à la plateforme."
                icon="briefcase"
                action={<Button icon={<span aria-hidden="true">＋</span>} onClick={() => setCreateOpen(true)}>Nouvelle organisation</Button>}
            />

            <OrganizationsStats organizations={organizations} />

            <Card className="sa-management-table-card" padding="medium">
                <div className="sa-management-toolbar">
                    <div><h2>Liste des organisations</h2><p>Les données affichées sont une maquette locale.</p></div>
                    <div className="sa-management-filters">
                        <SearchInput value={search} onSearch={setSearch} placeholder="Rechercher une organisation..." fullWidth />
                        <Select aria-label="Filtrer par statut" value={statusFilter} onChange={(event) => setStatusFilter(event.target.value as OrganizationStatusFilter)} options={[{ value: 'all', label: 'Tous les statuts' }, { value: 'active', label: 'Active' }, { value: 'trial', label: 'Essai' }, { value: 'suspended', label: 'Suspendue' }]} />
                    </div>
                </div>
                <DataTable columns={columns} data={filteredOrganizations} pageSize={6} initialSortKey="name" />
            </Card>

            <Modal isOpen={isCreateOpen} onClose={() => setCreateOpen(false)} title="Créer une organisation" size="medium" footer={<><Button variant="outline" onClick={() => setCreateOpen(false)}>Annuler</Button><Button type="submit" form="organization-create-form">Créer l’organisation</Button></>}>
                <form id="organization-create-form" className="sa-management-form" onSubmit={handleCreate}>
                    <p className="sa-management-form__hint">Cette action ajoute une ligne de démonstration à la maquette locale.</p>
                    <FormField label="Nom de l’organisation" htmlFor="organization-name" required><Input id="organization-name" value={name} onChange={(event) => setName(event.target.value)} required placeholder="Ex. Kinshasa Immobilier" fullWidth /></FormField>
                    <FormField label="Ville" htmlFor="organization-city" required><Select id="organization-city" value={city} onChange={(event) => setCity(event.target.value)} options={['Kinshasa', 'Lubumbashi', 'Goma', 'Bukavu', 'Matadi'].map((value) => ({ value, label: value }))} fullWidth /></FormField>
                    <FormField label="Offre" htmlFor="organization-plan"><Select id="organization-plan" value={plan} onChange={(event) => setPlan(event.target.value)} options={['Starter', 'Pro', 'Enterprise'].map((value) => ({ value, label: value }))} fullWidth /></FormField>
                </form>
            </Modal>
        </div>
    );
}
