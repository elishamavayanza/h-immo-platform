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
import { Textarea } from '../../../../components/Forms/Textarea';
import { FormField } from '../../../../components/Forms/FormField';
import { Modal } from '../../../../components/UI/Modal';
import { ConfirmDialog } from '../../../../components/UI/ConfirmDialog';
import { PopoverMenu } from '../../../../components/UI/PopoverMenu';
import type { PopoverMenuItem } from '../../../../hook-components/UI/PopoverMenu';
import type { OrganizationRole } from '../../../../../services/api/api.types';
import { canDo } from '../../../shared/permissions';
import { fieldErrorMap } from '../../../shared/actionErrors';
import { ModalActions } from '../../../shared/components/ModalActions';
import type { BuildingItem, ParcelItem } from '../../shared/types/reference.types';
import type { BuildingPayload } from '../services/patrimoineService';

const BUILDING_TYPE_OPTIONS = [
    { value: 'apartment', label: 'Immeuble à appartements' },
    { value: 'commercial', label: 'Commercial' },
    { value: 'office', label: 'Bureaux' },
    { value: 'restaurant', label: 'Restaurant' },
    { value: 'mixed', label: 'Mixte' },
];

const BUILDING_TYPE_LABELS: Record<string, string> = Object.fromEntries(BUILDING_TYPE_OPTIONS.map((option) => [option.value, option.label]));

interface BuildingForm {
    parcelUuid: string;
    reference: string;
    name: string;
    type: string;
    numberOfFloors: string;
    description: string;
}

const EMPTY_BUILDING_FORM: BuildingForm = { parcelUuid: '', reference: '', name: '', type: 'apartment', numberOfFloors: '', description: '' };

function buildingToForm(building: BuildingItem): BuildingForm {
    return {
        parcelUuid: building.parcelId,
        reference: building.reference,
        name: building.name,
        type: building.type,
        numberOfFloors: building.numberOfFloors !== null ? String(building.numberOfFloors) : '',
        description: building.description ?? '',
    };
}

interface BatimentsTabProps {
    buildings: BuildingItem[];
    parcels: ParcelItem[];
    role: OrganizationRole | null;
    isLoading: boolean;
    error: string | null;    note?: string | null;
    onReload: () => void;
    onCreate: (payload: BuildingPayload) => Promise<void>;
    onUpdate: (uuid: string, payload: BuildingPayload) => Promise<void>;
    onDelete: (uuid: string) => Promise<void>;
}

/**
 * Onglet Bâtiments : CRUD du 3ᵉ niveau du patrimoine.
 * La parcelle est choisie à la création puis figée (rattachement non
 * modifiable côté backend).
 */
export function BatimentsTab({ buildings, parcels, role, isLoading, error, note, onReload, onCreate, onUpdate, onDelete }: BatimentsTabProps) {
    const [search, setSearch] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const [editTarget, setEditTarget] = useState<BuildingItem | null>(null);
    const [form, setForm] = useState<BuildingForm>(EMPTY_BUILDING_FORM);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<BuildingItem | null>(null);

    const parcelById = new Map(parcels.map((parcel) => [parcel.id, parcel]));
    const parcelOptions = parcels.map((parcel) => ({ value: parcel.id, label: `${parcel.name} · ${parcel.reference}` }));

    const filtered = buildings.filter((building) => {
        const query = search.trim().toLocaleLowerCase('fr');
        return !query || [building.name, building.reference, parcelById.get(building.parcelId)?.name ?? ''].some((value) => value.toLocaleLowerCase('fr').includes(query));
    });

    const openCreate = () => {
        setForm({ ...EMPTY_BUILDING_FORM, parcelUuid: parcels[0]?.id ?? '' });
        setFormErrors({});
        setModalOpen(true);
    };

    const openEdit = (target: BuildingItem) => {
        setForm(buildingToForm(target));
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
        if (!form.parcelUuid) {
            setFormErrors({ parcelUuid: 'Sélectionnez une parcelle.' });
            return;
        }
        setSubmitting(true);
        setFormErrors({});
        const payload: BuildingPayload = {
            parcelUuid: form.parcelUuid,
            reference: form.reference.trim(),
            name: form.name.trim(),
            type: form.type,
            numberOfFloors: form.numberOfFloors.trim() === '' ? null : Number(form.numberOfFloors),
            description: form.description.trim() || null,
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

    const columns: DataTableColumn<BuildingItem>[] = [
        {
            key: 'name',
            title: 'Bâtiment',
            sortable: true,
            render: (building) => <div className="organization-property-name"><strong>{building.name}</strong><small>{building.reference}</small></div>,
        },
        { key: 'parcel', title: 'Parcelle', sortable: true, render: (building) => parcelById.get(building.parcelId)?.name ?? '—' },
        { key: 'type', title: 'Type', sortable: true, render: (building) => BUILDING_TYPE_LABELS[building.type] ?? building.type },
        { key: 'floors', title: 'Niveaux', render: (building) => (building.numberOfFloors !== null ? String(building.numberOfFloors) : '—') },
        {
            key: 'actions',
            title: 'Actions',
            render: (building) => {
                const items: PopoverMenuItem[] = [
                    ...(canDo(role, 'update_building')
                        ? [{ id: 'edit', label: 'Modifier', icon: <span aria-hidden="true">✎</span>, onClick: () => openEdit(building) }]
                        : []),
                    ...(canDo(role, 'delete_building')
                        ? [{ id: 'delete', label: 'Supprimer', icon: <span aria-hidden="true">🗑</span>, danger: true, onClick: () => setDeleteTarget(building) }]
                        : []),
                ];

                if (items.length === 0) return '—';

                return <PopoverMenu placement="bottom" offset={6} items={items} trigger={<span className="organization-row-actions" aria-label={`Actions pour ${building.name}`}><span aria-hidden="true">•••</span></span>} />;
            },
        },
    ];

    let body = <DataTable columns={columns} data={filtered} pageSize={12} initialSortKey="name" />;
    if (isLoading) {
        body = <EmptyState icon={<Spinner size="large" />} title="Chargement des bâtiments…" description="Récupération des immeubles de l’organisation." />;
    } else if (error) {
        body = <EmptyState title="Bâtiments indisponibles" description={error} action={<Button onClick={onReload}>Réessayer</Button>} />;
    } else if (filtered.length === 0) {
        body = <EmptyState
            title="Aucun bâtiment"
            description={buildings.length > 0 ? 'Aucun bâtiment ne correspond à cette recherche.' : 'Ajoutez un bâtiment dans une parcelle pour héberger des unités.'}
        />;
    }

    return (
        <Card className="organization-table-card" padding="medium">
            <div className="organization-table-toolbar">
                <div><h2>Bâtiments</h2><p>{note ?? 'Chaque bâtiment est rattaché à une parcelle et regroupe les unités locatives.'}</p></div>
                <div className="organization-filters">
                    <SearchInput value={search} onSearch={setSearch} placeholder="Rechercher un bâtiment…" fullWidth />
                    <span />
                    {canDo(role, 'create_building') && <Button onClick={openCreate} disabled={parcels.length === 0}>＋ Ajouter un bâtiment</Button>}
                </div>
            </div>
            {body}

            <Modal
                isOpen={modalOpen || editTarget !== null}
                onClose={closeModal}
                title={editTarget ? `Modifier « ${editTarget.name} »` : 'Ajouter un bâtiment'}
                size="medium"
                footer={<ModalActions formId="building-form" onCancel={closeModal} submitLabel={editTarget ? 'Enregistrer' : 'Créer le bâtiment'} loadingLabel={editTarget ? 'Enregistrement…' : 'Création…'} isLoading={submitting} />}
            >
                <form id="building-form" className="organization-management-form" onSubmit={submit}>
                    <FormField label="Parcelle" htmlFor="building-parcel" required helpText={editTarget ? 'Le rattachement à une parcelle n’est pas modifiable.' : undefined} error={formErrors.parcelUuid}>
                        <Select id="building-parcel" value={form.parcelUuid} onChange={(event) => setForm((current) => ({ ...current, parcelUuid: event.target.value }))} options={parcelOptions} placeholder="Sélectionner une parcelle" disabled={editTarget !== null} />
                    </FormField>
                    <FormField label="Référence" htmlFor="building-reference" required helpText="Unique par parcelle." error={formErrors.reference}>
                        <Input id="building-reference" value={form.reference} onChange={(event) => setForm((current) => ({ ...current, reference: event.target.value }))} placeholder="Ex. : BAT-A" required fullWidth maxLength={50} />
                    </FormField>
                    <FormField label="Nom" htmlFor="building-name" required error={formErrors.name}>
                        <Input id="building-name" value={form.name} onChange={(event) => setForm((current) => ({ ...current, name: event.target.value }))} placeholder="Ex. : Immeuble Katindo A" required fullWidth maxLength={150} />
                    </FormField>
                    <FormField label="Type" htmlFor="building-type" required error={formErrors.type}>
                        <Select id="building-type" value={form.type} onChange={(event) => setForm((current) => ({ ...current, type: event.target.value }))} options={BUILDING_TYPE_OPTIONS} />
                    </FormField>
                    <FormField label="Nombre de niveaux" htmlFor="building-floors" error={formErrors.numberOfFloors}>
                        <Input id="building-floors" type="number" min="0" step="1" inputMode="numeric" value={form.numberOfFloors} onChange={(event) => setForm((current) => ({ ...current, numberOfFloors: event.target.value }))} placeholder="Ex. : 3" fullWidth />
                    </FormField>
                    <FormField label="Description" htmlFor="building-description" error={formErrors.description}>
                        <Textarea id="building-description" value={form.description} onChange={(event) => setForm((current) => ({ ...current, description: event.target.value }))} rows={3} fullWidth maxLength={1000} />
                    </FormField>
                </form>
            </Modal>

            <ConfirmDialog
                isOpen={deleteTarget !== null}
                onClose={() => setDeleteTarget(null)}
                onConfirm={() => void confirmDelete()}
                title="Supprimer le bâtiment"
                message={deleteTarget ? `« ${deleteTarget.name} » sera retiré de votre patrimoine. Les unités rattachées restent conservées.` : ''}
                confirmLabel="Supprimer"
                cancelLabel="Annuler"
            />
        </Card>
    );
}
