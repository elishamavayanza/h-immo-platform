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
import { Badge } from '../../../../components/UI/Badge';
import { PopoverMenu } from '../../../../components/UI/PopoverMenu';
import type { PopoverMenuItem } from '../../../../hook-components/UI/PopoverMenu';
import type { OrganizationRole } from '../../../../../services/api/api.types';
import { formatMoney } from '../../../../../utils/format.utils';
import { canDo } from '../../../shared/permissions';
import { fieldErrorMap } from '../../../shared/actionErrors';
import { ModalActions } from '../../../shared/components/ModalActions';
import type { BuildingItem, UnitItem } from '../../shared/types/reference.types';
import type { UnitPayload } from '../services/patrimoineService';

const UNIT_TYPE_OPTIONS = [
    { value: 'apartment', label: 'Appartement' },
    { value: 'house', label: 'Maison' },
    { value: 'shop', label: 'Magasin' },
    { value: 'office', label: 'Bureau' },
    { value: 'restaurant', label: 'Restaurant' },
    { value: 'other', label: 'Autre' },
];

const UNIT_TYPE_LABELS: Record<string, string> = Object.fromEntries(UNIT_TYPE_OPTIONS.map((option) => [option.value, option.label]));

const CURRENCY_OPTIONS = [
    { value: 'USD', label: 'USD' },
    { value: 'CDF', label: 'CDF' },
];

interface UnitForm {
    buildingUuid: string;
    reference: string;
    type: string;
    floor: string;
    surface: string;
    bedrooms: string;
    rooms: string;
    bathrooms: string;
    monthlyRent: string;
    currency: string;
    description: string;
}

const EMPTY_UNIT_FORM: UnitForm = { buildingUuid: '', reference: '', type: 'apartment', floor: '0', surface: '', bedrooms: '', rooms: '', bathrooms: '', monthlyRent: '', currency: 'USD', description: '' };

function unitToForm(unit: UnitItem): UnitForm {
    return {
        buildingUuid: unit.buildingId,
        reference: unit.reference,
        type: unit.type,
        floor: String(unit.floor),
        surface: unit.surface,
        bedrooms: unit.bedrooms !== null ? String(unit.bedrooms) : '',
        rooms: unit.rooms !== null ? String(unit.rooms) : '',
        bathrooms: unit.bathrooms !== null ? String(unit.bathrooms) : '',
        monthlyRent: unit.monthlyRent,
        currency: unit.currency,
        description: unit.description ?? '',
    };
}

const optionalInteger = (value: string): number | null => (value.trim() === '' ? null : Number(value));

interface UnitesTabProps {
    units: UnitItem[];
    buildings: BuildingItem[];
    role: OrganizationRole | null;
    isLoading: boolean;
    error: string | null;    note?: string | null;
    onReload: () => void;
    onCreate: (payload: UnitPayload) => Promise<void>;
    onUpdate: (uuid: string, payload: UnitPayload) => Promise<void>;
    onDelete: (uuid: string) => Promise<void>;
    onPublish: (unit: UnitItem) => Promise<void>;
}

/**
 * Onglet Unités : CRUD du niveau feuille + bascule de publication vitrine.
 * Le bâtiment est figé après création. « Publier / Retirer » suit l'état
 * courant (`isPublished`) ; une unité occupée est refusée par le backend (422).
 */
export function UnitesTab({ units, buildings, role, isLoading, error, note, onReload, onCreate, onUpdate, onDelete, onPublish }: UnitesTabProps) {
    const [search, setSearch] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const [editTarget, setEditTarget] = useState<UnitItem | null>(null);
    const [form, setForm] = useState<UnitForm>(EMPTY_UNIT_FORM);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<UnitItem | null>(null);

    const buildingById = new Map(buildings.map((building) => [building.id, building]));
    const buildingOptions = buildings.map((building) => ({ value: building.id, label: `${building.name} · ${building.reference}` }));

    const filtered = units.filter((unit) => {
        const query = search.trim().toLocaleLowerCase('fr');
        return !query || [unit.reference, buildingById.get(unit.buildingId)?.name ?? ''].some((value) => value.toLocaleLowerCase('fr').includes(query));
    });

    const openCreate = () => {
        setForm({ ...EMPTY_UNIT_FORM, buildingUuid: buildings[0]?.id ?? '' });
        setFormErrors({});
        setModalOpen(true);
    };

    const openEdit = (target: UnitItem) => {
        setForm(unitToForm(target));
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
        if (!form.buildingUuid) {
            setFormErrors({ buildingUuid: 'Sélectionnez un bâtiment.' });
            return;
        }
        setSubmitting(true);
        setFormErrors({});
        const payload: UnitPayload = {
            buildingUuid: form.buildingUuid,
            reference: form.reference.trim(),
            type: form.type,
            floor: Number(form.floor || '0'),
            surface: form.surface.trim(),
            bedrooms: optionalInteger(form.bedrooms),
            rooms: optionalInteger(form.rooms),
            bathrooms: optionalInteger(form.bathrooms),
            monthlyRent: form.monthlyRent.trim(),
            currency: form.currency,
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

    const columns: DataTableColumn<UnitItem>[] = [
        {
            key: 'reference',
            title: 'Unité',
            sortable: true,
            render: (unit) => <div className="organization-property-name"><strong>{unit.reference}</strong><small>{UNIT_TYPE_LABELS[unit.type] ?? unit.type} · niveau {unit.floor}</small></div>,
        },
        { key: 'building', title: 'Bâtiment', sortable: true, render: (unit) => buildingById.get(unit.buildingId)?.name ?? '—' },
        { key: 'rent', title: 'Loyer mensuel', sortable: true, render: (unit) => formatMoney(unit.monthlyRent, unit.currency) },
        {
            key: 'published',
            title: 'Vitrine',
            sortable: true,
            render: (unit) => <Badge variant={unit.isPublished ? 'success' : 'secondary'}>{unit.isPublished ? 'Publiée' : 'Non publiée'}</Badge>,
        },
        {
            key: 'actions',
            title: 'Actions',
            render: (unit) => {
                const items: PopoverMenuItem[] = [
                    ...(canDo(role, 'update_unit')
                        ? [{ id: 'edit', label: 'Modifier', icon: <span aria-hidden="true">✎</span>, onClick: () => openEdit(unit) }]
                        : []),
                    ...(canDo(role, 'publish_listing')
                        ? [{ id: 'publish', label: unit.isPublished ? 'Retirer de la vitrine' : 'Publier', icon: <span aria-hidden="true">☁</span>, onClick: () => void onPublish(unit) }]
                        : []),
                    ...(canDo(role, 'delete_unit')
                        ? [{ id: 'delete', label: 'Supprimer', icon: <span aria-hidden="true">🗑</span>, danger: true, onClick: () => setDeleteTarget(unit) }]
                        : []),
                ];

                if (items.length === 0) return '—';

                return <PopoverMenu placement="bottom" offset={6} items={items} trigger={<span className="organization-row-actions" aria-label={`Actions pour ${unit.reference}`}><span aria-hidden="true">•••</span></span>} />;
            },
        },
    ];

    let body = <DataTable columns={columns} data={filtered} pageSize={12} initialSortKey="reference" />;
    if (isLoading) {
        body = <EmptyState icon={<Spinner size="large" />} title="Chargement des unités…" description="Récupération des unités locatives de l’organisation." />;
    } else if (error) {
        body = <EmptyState title="Unités indisponibles" description={error} action={<Button onClick={onReload}>Réessayer</Button>} />;
    } else if (filtered.length === 0) {
        body = <EmptyState
            title="Aucune unité"
            description={units.length > 0 ? 'Aucune unité ne correspond à cette recherche.' : 'Ajoutez une unité dans un bâtiment pour commencer à la louer.'}
        />;
    }

    return (
        <Card className="organization-table-card" padding="medium">
            <div className="organization-table-toolbar">
                <div><h2>Unités locatives</h2><p>{note ?? 'Chaque unité est rattachée à un bâtiment et peut être publiée sur la vitrine.'}</p></div>
                <div className="organization-filters">
                    <SearchInput value={search} onSearch={setSearch} placeholder="Rechercher une unité…" fullWidth />
                    <span />
                    {canDo(role, 'create_unit') && <Button onClick={openCreate} disabled={buildings.length === 0}>＋ Ajouter une unité</Button>}
                </div>
            </div>
            {body}

            <Modal
                isOpen={modalOpen || editTarget !== null}
                onClose={closeModal}
                title={editTarget ? `Modifier « ${editTarget.reference} »` : 'Ajouter une unité'}
                size="medium"
                footer={<ModalActions formId="unit-form" onCancel={closeModal} submitLabel={editTarget ? 'Enregistrer' : 'Créer l’unité'} loadingLabel={editTarget ? 'Enregistrement…' : 'Création…'} isLoading={submitting} />}
            >
                <form id="unit-form" className="organization-management-form" onSubmit={submit}>
                    <FormField label="Bâtiment" htmlFor="unit-building" required helpText={editTarget ? 'Le rattachement à un bâtiment n’est pas modifiable.' : undefined} error={formErrors.buildingUuid}>
                        <Select id="unit-building" value={form.buildingUuid} onChange={(event) => setForm((current) => ({ ...current, buildingUuid: event.target.value }))} options={buildingOptions} placeholder="Sélectionner un bâtiment" disabled={editTarget !== null} />
                    </FormField>
                    <FormField label="Référence" htmlFor="unit-reference" required helpText="Unique par bâtiment." error={formErrors.reference}>
                        <Input id="unit-reference" value={form.reference} onChange={(event) => setForm((current) => ({ ...current, reference: event.target.value }))} placeholder="Ex. : A-101" required fullWidth maxLength={50} />
                    </FormField>
                    <FormField label="Type" htmlFor="unit-type" required error={formErrors.type}>
                        <Select id="unit-type" value={form.type} onChange={(event) => setForm((current) => ({ ...current, type: event.target.value }))} options={UNIT_TYPE_OPTIONS} />
                    </FormField>
                    <FormField label="Niveau / étage" htmlFor="unit-floor" required error={formErrors.floor}>
                        <Input id="unit-floor" type="number" step="1" inputMode="numeric" value={form.floor} onChange={(event) => setForm((current) => ({ ...current, floor: event.target.value }))} placeholder="Ex. : 1" required fullWidth />
                    </FormField>
                    <FormField label="Surface (m²)" htmlFor="unit-surface" required error={formErrors.surface}>
                        <Input id="unit-surface" type="number" min="0" step="0.01" inputMode="decimal" value={form.surface} onChange={(event) => setForm((current) => ({ ...current, surface: event.target.value }))} placeholder="Ex. : 85" required fullWidth />
                    </FormField>
                    <FormField label="Chambres" htmlFor="unit-bedrooms" error={formErrors.bedrooms}>
                        <Input id="unit-bedrooms" type="number" min="0" step="1" inputMode="numeric" value={form.bedrooms} onChange={(event) => setForm((current) => ({ ...current, bedrooms: event.target.value }))} fullWidth />
                    </FormField>
                    <FormField label="Pièces" htmlFor="unit-rooms" error={formErrors.rooms}>
                        <Input id="unit-rooms" type="number" min="0" step="1" inputMode="numeric" value={form.rooms} onChange={(event) => setForm((current) => ({ ...current, rooms: event.target.value }))} fullWidth />
                    </FormField>
                    <FormField label="Salles de bain" htmlFor="unit-bathrooms" error={formErrors.bathrooms}>
                        <Input id="unit-bathrooms" type="number" min="0" step="1" inputMode="numeric" value={form.bathrooms} onChange={(event) => setForm((current) => ({ ...current, bathrooms: event.target.value }))} fullWidth />
                    </FormField>
                    <FormField label="Loyer mensuel" htmlFor="unit-rent" required error={formErrors.monthlyRent}>
                        <Input id="unit-rent" type="number" min="0" step="0.01" inputMode="decimal" value={form.monthlyRent} onChange={(event) => setForm((current) => ({ ...current, monthlyRent: event.target.value }))} placeholder="Ex. : 350" required fullWidth />
                    </FormField>
                    <FormField label="Devise" htmlFor="unit-currency" required error={formErrors.currency}>
                        <Select id="unit-currency" value={form.currency} onChange={(event) => setForm((current) => ({ ...current, currency: event.target.value }))} options={CURRENCY_OPTIONS} />
                    </FormField>
                    <FormField label="Description" htmlFor="unit-description" error={formErrors.description}>
                        <Textarea id="unit-description" value={form.description} onChange={(event) => setForm((current) => ({ ...current, description: event.target.value }))} rows={3} fullWidth maxLength={1000} />
                    </FormField>
                </form>
            </Modal>

            <ConfirmDialog
                isOpen={deleteTarget !== null}
                onClose={() => setDeleteTarget(null)}
                onConfirm={() => void confirmDelete()}
                title="Supprimer l’unité"
                message={deleteTarget ? `« ${deleteTarget.reference} » sera retirée de votre patrimoine.` : ''}
                confirmLabel="Supprimer"
                cancelLabel="Annuler"
            />
        </Card>
    );
}
