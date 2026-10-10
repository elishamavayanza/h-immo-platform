import { useState, type FormEvent } from 'react';

import { Card } from '../../../../components/UI/Card';
import { Button } from '../../../../components/UI/Button';
import { Spinner } from '../../../../components/UI/Spinner';
import { EmptyState } from '../../../../components/Data/EmptyState';
import { DataTable } from '../../../../components/Data/DataTable';
import type { DataTableColumn } from '../../../../hook-components/Data/DataTable';
import { SearchInput } from '../../../../components/Forms/SearchInput';
import { Select } from '../../../../components/Forms/Select';
import { Input } from '../../../../components/Forms/Input';
import { FormField } from '../../../../components/Forms/FormField';
import { Modal } from '../../../../components/UI/Modal';
import { ConfirmDialog } from '../../../../components/UI/ConfirmDialog';
import { Badge } from '../../../../components/UI/Badge';
import { PopoverMenu } from '../../../../components/UI/PopoverMenu';
import type { PopoverMenuItem } from '../../../../hook-components/UI/PopoverMenu';
import type { OrganizationRole } from '../../../../../services/api/api.types';
import { canDo } from '../../../shared/permissions';
import { fieldErrorMap } from '../../../shared/actionErrors';
import { ModalActions } from '../../../shared/components/ModalActions';
import type { CityPayload } from '../services/patrimoineService';
import type { CityItem } from '../../shared/types/reference.types';

const CITY_STATUS_OPTIONS = [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

function cityToForm(city: CityItem) {
    return {
        name: city.name,
        code: city.code,
        province: city.province ?? '',
        country: city.country ?? '',
        status: city.status === 'inactive' ? 'inactive' : 'active',
    };
}

const EMPTY_CITY_FORM = { name: '', code: '', province: '', country: '', status: 'active' };

interface VillesTabProps {
    cities: CityItem[];
    role: OrganizationRole | null;
    isLoading: boolean;
    error: string | null;    note?: string | null;
    onReload: () => void;
    onCreate: (payload: CityPayload) => Promise<void>;
    onUpdate: (uuid: string, payload: CityPayload) => Promise<void>;
    onSetStatus: (city: CityItem, status: CityPayload['status']) => Promise<void>;
    onDelete: (uuid: string) => Promise<void>;
}

/**
 * Onglet Villes : liste, recherche et CRUD du niveau racine du patrimoine.
 *
 * L'onglet est une unité fonctionnelle autonome : il porte son propre état de
 * formulaire, sa modale et sa confirmation, et délègue toute écriture au hook
 * parent. Les boutons sont gatés par `canDo` (miroir de `SecurityService`) ;
 * l'API demeure l'autorité.
 */
export function VillesTab({ cities, role, isLoading, error, note, onReload, onCreate, onUpdate, onSetStatus, onDelete }: VillesTabProps) {
    const [search, setSearch] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const [editTarget, setEditTarget] = useState<CityItem | null>(null);
    const [form, setForm] = useState(EMPTY_CITY_FORM);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<CityItem | null>(null);

    const filtered = cities.filter((entry) => {
        const query = search.trim().toLocaleLowerCase('fr');
        return !query || [entry.name, entry.code].some((value) => value.toLocaleLowerCase('fr').includes(query));
    });

    const openCreate = () => {
        setForm(EMPTY_CITY_FORM);
        setFormErrors({});
        setModalOpen(true);
    };

    const openEdit = (target: CityItem) => {
        setForm(cityToForm(target));
        setFormErrors({});
        setEditTarget(target);
    };

    const closeModal = () => {
        setModalOpen(false);
        setEditTarget(null);
    };

    const submit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (submitting) return;
        setSubmitting(true);
        setFormErrors({});
        const payload: CityPayload = {
            name: form.name.trim(),
            code: form.code.trim(),
            province: form.province.trim() || null,
            country: form.country.trim() || null,
            status: form.status === 'inactive' ? 'inactive' : 'active',
        };
        try {
            if (editTarget) {
                await onUpdate(editTarget.id, payload);
            } else {
                await onCreate(payload);
            }
            closeModal();
        } catch (cause) {
            setFormErrors(fieldErrorMap(cause));
        } finally {
            setSubmitting(false);
        }
    };

    const confirmDelete = async () => {
        if (!deleteTarget) return;
        const target = deleteTarget;
        setDeleteTarget(null);
        await onDelete(target.id);
    };

    const columns: DataTableColumn<CityItem>[] = [
        {
            key: 'name',
            title: 'Ville',
            sortable: true,
            render: (city) => <div className="organization-property-name"><strong>{city.name}</strong><small>{city.code}</small></div>,
        },
        { key: 'province', title: 'Province', sortable: true, render: (city) => city.province || '—' },
        { key: 'country', title: 'Pays', sortable: true, render: (city) => city.country || '—' },
        {
            key: 'status',
            title: 'Statut',
            sortable: true,
            render: (city) => <Badge variant={city.status === 'active' ? 'success' : 'secondary'}>{city.status === 'active' ? 'Active' : 'Inactive'}</Badge>,
        },
        {
            key: 'actions',
            title: 'Actions',
            render: (city) => {
                const isActive = city.status === 'active';
                const items: PopoverMenuItem[] = [
                    ...(canDo(role, 'update_city')
                        ? [{ id: 'edit', label: 'Modifier', icon: <span aria-hidden="true">✎</span>, onClick: () => openEdit(city) }]
                        : []),
                    ...(canDo(role, isActive ? 'deactivate_city' : 'activate_city')
                        ? [{ id: 'toggle', label: isActive ? 'Désactiver' : 'Activer', icon: <span aria-hidden="true">⏻</span>, onClick: () => void onSetStatus(city, isActive ? 'inactive' : 'active') }]
                        : []),
                    ...(canDo(role, 'delete_city')
                        ? [{ id: 'delete', label: 'Supprimer', icon: <span aria-hidden="true">🗑</span>, danger: true, onClick: () => setDeleteTarget(city) }]
                        : []),
                ];

                if (items.length === 0) return '—';

                return <PopoverMenu placement="bottom" offset={6} items={items} trigger={<span className="organization-row-actions" aria-label={`Actions pour ${city.name}`}><span aria-hidden="true">•••</span></span>} />;
            },
        },
    ];

    let body = <DataTable columns={columns} data={filtered} pageSize={12} initialSortKey="name" />;
    if (isLoading) {
        body = <EmptyState icon={<Spinner size="large" />} title="Chargement des villes…" description="Récupération des villes de l’organisation." />;
    } else if (error) {
        body = <EmptyState title="Villes indisponibles" description={error} action={<Button onClick={onReload}>Réessayer</Button>} />;
    } else if (filtered.length === 0) {
        body = <EmptyState
            title="Aucune ville"
            description={cities.length > 0 ? 'Aucune ville ne correspond à cette recherche.' : 'Ajoutez une première ville pour commencer à structurer votre patrimoine.'}
        />;
    }

    return (
        <Card className="organization-table-card" padding="medium">
            <div className="organization-table-toolbar">
                <div><h2>Villes d’exploitation</h2><p>{note ?? 'Créez et organisez les villes qui structurent votre patrimoine.'}</p></div>
                <div className="organization-filters">
                    <SearchInput value={search} onSearch={setSearch} placeholder="Rechercher une ville…" fullWidth />
                    <span />
                    {canDo(role, 'create_city') && <Button onClick={openCreate}>＋ Ajouter une ville</Button>}
                </div>
            </div>
            {body}

            <Modal
                isOpen={modalOpen || editTarget !== null}
                onClose={closeModal}
                title={editTarget ? `Modifier « ${editTarget.name} »` : 'Ajouter une ville'}
                size="medium"
                footer={<ModalActions formId="city-form" onCancel={closeModal} submitLabel={editTarget ? 'Enregistrer' : 'Créer la ville'} loadingLabel={editTarget ? 'Enregistrement…' : 'Création…'} isLoading={submitting} />}
            >
                <form id="city-form" className="organization-management-form" onSubmit={submit}>
                    <p className="organization-management-form__hint">Une ville est la racine du patrimoine : parcelles, immeubles et unités y sont rattachés.</p>
                    <FormField label="Nom de la ville" htmlFor="city-name" required error={formErrors.name}>
                        <Input id="city-name" value={form.name} onChange={(event) => setForm((current) => ({ ...current, name: event.target.value }))} placeholder="Ex. : Goma" required fullWidth maxLength={100} />
                    </FormField>
                    <FormField label="Code / trigramme" htmlFor="city-code" required helpText="Unique par organisation." error={formErrors.code}>
                        <Input id="city-code" value={form.code} onChange={(event) => setForm((current) => ({ ...current, code: event.target.value }))} placeholder="Ex. : GOM" required fullWidth maxLength={30} />
                    </FormField>
                    <FormField label="Province" htmlFor="city-province" error={formErrors.province}>
                        <Input id="city-province" value={form.province} onChange={(event) => setForm((current) => ({ ...current, province: event.target.value }))} placeholder="Ex. : Nord-Kivu" fullWidth maxLength={100} />
                    </FormField>
                    <FormField label="Pays" htmlFor="city-country" error={formErrors.country}>
                        <Input id="city-country" value={form.country} onChange={(event) => setForm((current) => ({ ...current, country: event.target.value }))} placeholder="Ex. : RDC" fullWidth maxLength={100} />
                    </FormField>
                    <FormField label="Statut" htmlFor="city-status" error={formErrors.status}>
                        <Select id="city-status" value={form.status} onChange={(event) => setForm((current) => ({ ...current, status: event.target.value }))} options={CITY_STATUS_OPTIONS} />
                    </FormField>
                </form>
            </Modal>

            <ConfirmDialog
                isOpen={deleteTarget !== null}
                onClose={() => setDeleteTarget(null)}
                onConfirm={() => void confirmDelete()}
                title="Supprimer la ville"
                message={deleteTarget ? `« ${deleteTarget.name} » sera retirée de votre patrimoine. Ses parcelles, immeubles et unités restent conservés.` : ''}
                confirmLabel="Supprimer"
                cancelLabel="Annuler"
            />
        </Card>
    );
}
