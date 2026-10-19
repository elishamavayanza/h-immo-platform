import { useEffect, useState, type FormEvent } from 'react';

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
import { Icon } from '../../../../components/UI/Icon/Icon';
import { PopoverMenu } from '../../../../components/UI/PopoverMenu';
import type { PopoverMenuItem } from '../../../../hook-components/UI/PopoverMenu';
import type { OrganizationRole } from '../../../../../services/api/api.types';
import { formatMoney } from '../../../../../utils/format.utils';
import { canDo } from '../../../shared/permissions';
import { fieldErrorMap } from '../../../shared/utils/actionErrors';
import { ModalActions } from '../../../shared/components/ModalActions';
import { changedFields } from '../../../shared/utils/changedFields';
import { PhotoGallery } from '../../../shared/media/components/PhotoGallery';
import { CURRENCY_OPTIONS, UNIT_OCCUPANCY_STATUS_LABELS, UNIT_OPERATIONAL_STATUS_LABELS, UNIT_OPERATIONAL_STATUS_OPTIONS, UNIT_TYPE_LABELS, UNIT_TYPE_OPTIONS } from './patrimoineView';
import type { BuildingItem, UnitItem } from '../../shared/types/reference.types';
import { createBuildingLevel, fetchBuildingLevels, updateBuildingLevel, type BuildingLevelItem, type BuildingLevelPayload, type UnitPayload } from '../services/patrimoineService';
import { AccessPointsManager } from './AccessPointsManager';

interface UnitForm {
    buildingUuid: string;
    reference: string;
    type: string;
    operationalStatus: string;
    commercialActivity: string;
    floor: string;
    floorLabel: string;
    parentUnitUuid: string;
    levelUuid: string;
    surface: string;
    bedrooms: string;
    rooms: string;
    bathrooms: string;
    monthlyRent: string;
    currency: string;
    description: string;
}

const EMPTY_UNIT_FORM: UnitForm = { buildingUuid: '', reference: '', type: 'apartment', operationalStatus: 'available', commercialActivity: '', floor: '0', floorLabel: '', parentUnitUuid: '', levelUuid: '', surface: '', bedrooms: '', rooms: '', bathrooms: '', monthlyRent: '', currency: 'USD', description: '' };

function unitToForm(unit: UnitItem): UnitForm {
    return {
        buildingUuid: unit.buildingId,
        reference: unit.reference,
        type: unit.type,
        operationalStatus: unit.operationalStatus,
        commercialActivity: unit.commercialActivity ?? '',
        floor: String(unit.floor),
        floorLabel: unit.floorLabel ?? '',
        parentUnitUuid: unit.parentUnitId ?? '',
        levelUuid: unit.levelId ?? '',
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
    onUpdate: (uuid: string, payload: Partial<UnitPayload>) => Promise<void>;
    onDelete: (uuid: string) => Promise<void>;
    onPublish: (unit: UnitItem) => Promise<void>;
    selectedBuildingId: string | null;
    onBuildingFilter: (uuid: string | null) => void;
    showcaseEnabled?: boolean;
    /** Lecture seule (espace PATRON) : masque toute action d'écriture. */
    readOnly?: boolean;
}

/**
 * Onglet Unités : CRUD du niveau feuille + bascule de publication vitrine.
 * Le bâtiment est figé après création. « Publier / Retirer » suit l'état
 * courant (`isPublished`) ; une unité occupée est refusée par le backend (422).
 * `readOnly` masque les écritures (Patron).
 */
export function UnitesTab({ units, buildings, role, isLoading, error, note, onReload, onCreate, onUpdate, onDelete, onPublish, selectedBuildingId, onBuildingFilter, showcaseEnabled = false, readOnly = false }: UnitesTabProps) {
    const [search, setSearch] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const [editTarget, setEditTarget] = useState<UnitItem | null>(null);
    const [form, setForm] = useState<UnitForm>(EMPTY_UNIT_FORM);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<UnitItem | null>(null);
    const [photoTarget, setPhotoTarget] = useState<UnitItem | null>(null);
    const [accessBuildingId, setAccessBuildingId] = useState<string | null>(null);
    const [levelBuildingId, setLevelBuildingId] = useState<string | null>(null);
    const [buildingLevels, setBuildingLevels] = useState<BuildingLevelItem[]>([]);
    const [levelForm, setLevelForm] = useState<BuildingLevelPayload>({ reference: '', label: '', displayOrder: 0, description: null });
    const [levelEdit, setLevelEdit] = useState<BuildingLevelItem | null>(null);
    const [levelErrors, setLevelErrors] = useState<Record<string, string>>({});
    const [savingLevel, setSavingLevel] = useState(false);
    const levelsBuildingId = levelBuildingId || form.buildingUuid || selectedBuildingId || '';

    useEffect(() => {
        let active = true;
        if (!levelsBuildingId) { setBuildingLevels([]); return () => { active = false; }; }
        void fetchBuildingLevels(levelsBuildingId).then((items) => { if (active) setBuildingLevels(items); }).catch(() => { if (active) setBuildingLevels([]); });
        return () => { active = false; };
    }, [levelsBuildingId]);

    const buildingById = new Map(buildings.map((building) => [building.id, building]));
    const buildingOptions = buildings.map((building) => ({ value: building.id, label: `${building.name} · ${building.reference}` }));
    const maximumFloorFor = (buildingUuid: string): number => {
        const building = buildingById.get(buildingUuid);
        if (building?.numberOfFloors !== null && building?.numberOfFloors !== undefined) return building.numberOfFloors;
        return Math.max(
            0,
            ...units.filter((unit) => unit.buildingId === buildingUuid).map((unit) => unit.floor),
            ...buildingLevels.map((level) => level.displayOrder),
        );
    };

    const filtered = units.filter((unit) => {
        const query = search.trim().toLocaleLowerCase('fr');
        return (selectedBuildingId === null || unit.buildingId === selectedBuildingId) && (!query || [unit.reference, buildingById.get(unit.buildingId)?.name ?? ''].some((value) => value.toLocaleLowerCase('fr').includes(query)));
    });

    const openCreate = () => {
        setForm({ ...EMPTY_UNIT_FORM, buildingUuid: selectedBuildingId ?? buildings[0]?.id ?? '' });
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
            operationalStatus: form.operationalStatus,
            commercialActivity: form.commercialActivity.trim(),
            floor: Number(form.floor || '0'),
            floorLabel: form.floorLabel.trim(),
            levelUuid: form.levelUuid || null,
            parentUnitUuid: form.parentUnitUuid,
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
                const initial = {
                    buildingUuid: editTarget.buildingId,
                    reference: editTarget.reference,
                    type: editTarget.type,
                    operationalStatus: editTarget.operationalStatus,
                    commercialActivity: editTarget.commercialActivity ?? '',
                    floor: editTarget.floor,
                    floorLabel: editTarget.floorLabel,
                    levelUuid: editTarget.levelId ?? '',
                    parentUnitUuid: editTarget.parentUnitId ?? '',
                    surface: editTarget.surface,
                    bedrooms: editTarget.bedrooms,
                    rooms: editTarget.rooms,
                    bathrooms: editTarget.bathrooms,
                    monthlyRent: editTarget.monthlyRent,
                    currency: editTarget.currency,
                    description: editTarget.description,
                };
                const changes = changedFields(payload, initial);
                if (Object.keys(changes).length > 0) await onUpdate(editTarget.id, changes);
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

    const editBuildingLevel = (level: BuildingLevelItem) => {
        setLevelEdit(level);
        setLevelForm({ reference: level.reference, label: level.label, displayOrder: level.displayOrder, description: level.description });
        setLevelErrors({});
    };

    const saveBuildingLevel = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!levelBuildingId || savingLevel) return;
        setSavingLevel(true); setLevelErrors({});
        try {
            const saved = levelEdit
                ? await updateBuildingLevel(levelEdit.id, levelForm)
                : await createBuildingLevel(levelBuildingId, levelForm);
            setBuildingLevels((current) => [...current.filter((level) => level.id !== saved.id), saved].sort((a, b) => a.displayOrder - b.displayOrder || a.label.localeCompare(b.label, 'fr')));
            setLevelEdit(null);
            setLevelForm({ reference: '', label: '', displayOrder: 0, description: null });
        } catch (cause) { setLevelErrors(fieldErrorMap(cause)); }
        finally { setSavingLevel(false); }
    };

    const columns: DataTableColumn<UnitItem>[] = [
        {
            key: 'reference',
            title: 'Unité',
            sortable: true,
            render: (unit) => <div className="organization-property-name"><strong>{unit.reference}</strong><small>{UNIT_TYPE_LABELS[unit.type] ?? unit.type} · {unit.floorLabel || (unit.floor === 0 ? 'Rez-de-chaussée' : 'Niveau ' + unit.floor)}{unit.parentUnitId ? ' · espace subdivisé' : ''}</small></div>,
        },
        { key: 'building', title: 'Bâtiment', sortable: true, render: (unit) => <button type="button" className="patrimoine-link" onClick={() => onBuildingFilter(unit.buildingId)}>{buildingById.get(unit.buildingId)?.name ?? '—'}</button> },
        { key: 'commercialActivity', title: 'Activité', render: (unit) => unit.commercialActivity ?? '—' },
        { key: 'occupancyStatus', title: 'Occupation', sortable: true, render: (unit) => <Badge variant={unit.occupancyStatus === 'vacant' ? 'success' : unit.occupancyStatus === 'occupied' ? 'warning' : 'secondary'}>{UNIT_OCCUPANCY_STATUS_LABELS[unit.occupancyStatus] ?? unit.occupancyStatus}</Badge> },
        { key: 'rent', title: 'Loyer mensuel', sortable: true, render: (unit) => formatMoney(unit.monthlyRent, unit.currency) },
        {
            key: 'operationalStatus',
            title: 'État opérationnel',
            sortable: true,
            render: (unit) => <Badge variant={unit.operationalStatus === 'available' ? 'success' : 'warning'}>{UNIT_OPERATIONAL_STATUS_LABELS[unit.operationalStatus] ?? unit.operationalStatus}</Badge>,
        },
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
                if (readOnly) return '—';
                const canManageShowcase = showcaseEnabled && canDo(role, 'publish_listing');

                const items: PopoverMenuItem[] = [
                    ...(canDo(role, 'update_unit')
                        ? [{ id: 'edit', label: 'Modifier', icon: <Icon name="edit" />, onClick: () => openEdit(unit) }]
                        : []),
                    ...(canManageShowcase
                        ? [{ id: 'photos', label: 'Gérer les photos', icon: <Icon name="camera" />, onClick: () => setPhotoTarget(unit) }]
                        : []),
                    ...(canManageShowcase
                        ? [{ id: 'publish', label: unit.isPublished ? 'Retirer de la vitrine' : 'Publier', icon: <Icon name="cloud" />, onClick: () => void onPublish(unit) }]
                        : []),
                    ...(canDo(role, 'delete_unit')
                        ? [{ id: 'delete', label: 'Supprimer', icon: <Icon name="trash" />, danger: true, onClick: () => setDeleteTarget(unit) }]
                        : []),
                ];

                if (items.length === 0) return '—';

                return <PopoverMenu placement="bottom" offset={6} items={items} trigger={<span className="organization-row-actions" aria-label={`Actions pour ${unit.reference}`}><Icon name="more" /></span>} />;
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
                <div className="organization-filters patrimoine-unit-filters">
                    <SearchInput value={search} onSearch={setSearch} placeholder="Rechercher une unité…" fullWidth />
                    <Select aria-label="Filtrer par bâtiment" value={selectedBuildingId ?? 'all'} onChange={(event) => onBuildingFilter(event.target.value === 'all' ? null : event.target.value)} options={[{ value: 'all', label: 'Tous les bâtiments' }, ...buildingOptions]} />
                    {!readOnly && canDo(role, 'create_unit') && <Button onClick={openCreate} disabled={buildings.length === 0}>＋ Ajouter une unité</Button>}
                </div>
            </div>
            {selectedBuildingId && <div className="patrimoine-context"><span>Bâtiment sélectionné : <strong>{buildingById.get(selectedBuildingId)?.name ?? 'Bâtiment'}</strong></span><div className="patrimoine-context__actions">{!readOnly && canDo(role, 'update_unit') && <><Button variant="outline" onClick={() => setLevelBuildingId(selectedBuildingId)}>Gérer les niveaux</Button><Button variant="outline" onClick={() => setAccessBuildingId(selectedBuildingId)}>Gérer les accès</Button></>}<Button variant="outline" onClick={() => onBuildingFilter(null)}>Effacer le filtre</Button></div></div>}
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
                    {['shop', 'office', 'restaurant', 'warehouse', 'workshop'].includes(form.type) && <FormField label="Activité exercée" htmlFor="unit-commercial-activity" helpText="Facultatif. Exemple : pharmacie, alimentation, cabinet médical ou atelier de couture." error={formErrors.commercialActivity}>
                        <Input id="unit-commercial-activity" value={form.commercialActivity} onChange={(event) => setForm((current) => ({ ...current, commercialActivity: event.target.value }))} placeholder="Ex. : Pharmacie" fullWidth maxLength={150} />
                    </FormField>}
                    <FormField label="État opérationnel" htmlFor="unit-operational-status" required helpText="L’occupation est calculée à partir des baux. Tout état autre que disponible bloque l’activation d’un nouveau bail." error={formErrors.operationalStatus}>
                        <Select id="unit-operational-status" value={form.operationalStatus} onChange={(event) => setForm((current) => ({ ...current, operationalStatus: event.target.value }))} options={UNIT_OPERATIONAL_STATUS_OPTIONS} />
                    </FormField>
                    <FormField label="Étage de l’unité" htmlFor="unit-floor" required helpText={`0 = rez-de-chaussée ; 1 = 1er étage ; −1 = sous-sol. Maximum supérieur pour ce bâtiment : ${maximumFloorFor(form.buildingUuid)}.`} error={formErrors.floor}>
                        <Input id="unit-floor" type="number" min="-1000" max={maximumFloorFor(form.buildingUuid)} step="1" inputMode="numeric" value={form.floor} onChange={(event) => setForm((current) => ({ ...current, floor: event.target.value }))} placeholder="Ex. : 1" required fullWidth disabled={Boolean(form.levelUuid)} />
                    </FormField>
                    <FormField label="Libellé du niveau" htmlFor="unit-floor-label" helpText="Facultatif : sous-sol, rez-de-chaussée, étage, mezzanine… Le numéro reste soumis à la capacité du bâtiment." error={formErrors.floorLabel}>
                        <Input id="unit-floor-label" value={form.floorLabel} onChange={(event) => setForm((current) => ({ ...current, floorLabel: event.target.value }))} placeholder="Ex. : Mezzanine" fullWidth maxLength={100} disabled={Boolean(form.levelUuid)} />
                    </FormField>
                    <FormField label="Niveau partagé (facultatif)" htmlFor="unit-level" helpText="Créez d’abord les niveaux depuis le bâtiment sélectionné. Leur numéro doit respecter la capacité du bâtiment ; leur libellé sera repris ici." error={formErrors.levelUuid}>
                        <Select id="unit-level" value={form.levelUuid} onChange={(event) => {
                            const level = buildingLevels.find((item) => item.id === event.target.value);
                            setForm((current) => ({ ...current, levelUuid: event.target.value, ...(level ? { floor: String(level.displayOrder), floorLabel: level.label } : {}) }));
                        }} options={buildingLevels.map((level) => ({ value: level.id, label: `${level.displayOrder} · ${level.label}` }))} placeholder="Aucun niveau déclaré" />
                    </FormField>
                    {form.buildingUuid && !readOnly && canDo(role, 'update_unit') && <Button type="button" variant="outline" onClick={() => setLevelBuildingId(form.buildingUuid)}>Gérer les niveaux du bâtiment</Button>}
                    <FormField label="Espace parent" htmlFor="unit-parent-space" helpText="Facultatif : utilisez-le pour représenter un espace imbriqué dans une maison ou un local." error={formErrors.parentUnitUuid}>
                        <Select id="unit-parent-space" value={form.parentUnitUuid} onChange={(event) => setForm((current) => ({ ...current, parentUnitUuid: event.target.value }))} options={[{ value: '', label: 'Aucun · espace indépendant' }, ...units.filter((unit) => unit.buildingId === form.buildingUuid && unit.id !== editTarget?.id).map((unit) => ({ value: unit.id, label: unit.reference + ' · ' + (UNIT_TYPE_LABELS[unit.type] ?? unit.type) }))]} />
                    </FormField>
                    <FormField label="Surface (m²)" htmlFor="unit-surface" required error={formErrors.surface}>
                        <Input id="unit-surface" type="number" min="0" step="0.01" inputMode="decimal" value={form.surface} onChange={(event) => setForm((current) => ({ ...current, surface: event.target.value }))} placeholder="Ex. : 85" required fullWidth />
                    </FormField>
                    <FormField label="Chambres à coucher" htmlFor="unit-bedrooms" helpText="Nombre descriptif : ces chambres ne deviennent pas des unités locatives." error={formErrors.bedrooms}>
                        <Input id="unit-bedrooms" type="number" min="0" step="1" inputMode="numeric" value={form.bedrooms} onChange={(event) => setForm((current) => ({ ...current, bedrooms: event.target.value }))} placeholder="Ex. : 2" fullWidth />
                    </FormField>
                    <FormField label="Pièces principales (chambres comprises)" htmlFor="unit-rooms" helpText="Total descriptif du logement. Les pièces ne sont pas créées individuellement." error={formErrors.rooms}>
                        <Input id="unit-rooms" type="number" min="0" step="1" inputMode="numeric" value={form.rooms} onChange={(event) => setForm((current) => ({ ...current, rooms: event.target.value }))} placeholder="Ex. : 4" fullWidth />
                    </FormField>
                    <FormField label="Salles de bain / d’eau" htmlFor="unit-bathrooms" error={formErrors.bathrooms}>
                        <Input id="unit-bathrooms" type="number" min="0" step="1" inputMode="numeric" value={form.bathrooms} onChange={(event) => setForm((current) => ({ ...current, bathrooms: event.target.value }))} placeholder="Ex. : 1" fullWidth />
                    </FormField>
                    <FormField label="Loyer mensuel" htmlFor="unit-rent" required error={formErrors.monthlyRent}>
                        <Input id="unit-rent" type="number" min="0" step="0.01" inputMode="decimal" value={form.monthlyRent} onChange={(event) => setForm((current) => ({ ...current, monthlyRent: event.target.value }))} placeholder="Ex. : 350" required fullWidth />
                    </FormField>
                    <FormField label="Devise" htmlFor="unit-currency" required error={formErrors.currency}>
                        <Select id="unit-currency" value={form.currency} onChange={(event) => setForm((current) => ({ ...current, currency: event.target.value }))} options={CURRENCY_OPTIONS} />
                    </FormField>
                    <FormField label="Description" htmlFor="unit-description" error={formErrors.description}>
                        <Textarea id="unit-description" value={form.description} onChange={(event) => setForm((current) => ({ ...current, description: event.target.value }))} placeholder="Ex. : Appartement lumineux avec balcon et cuisine équipée." rows={3} fullWidth maxLength={5000} />
                    </FormField>
                </form>
            </Modal>

            <ConfirmDialog
                isOpen={deleteTarget !== null}
                onClose={() => setDeleteTarget(null)}
                onConfirm={() => void confirmDelete()}
                title="Supprimer l’unité"
                message={deleteTarget ? `« ${deleteTarget.reference} » ne peut être archivée que sans sous-unité active et sans bail actif sur elle, son parent ou ses sous-espaces.` : ''}
                confirmLabel="Supprimer"
                cancelLabel="Annuler"
            />

            <Modal isOpen={photoTarget !== null} onClose={() => setPhotoTarget(null)} title={photoTarget ? `Photos de « ${photoTarget.reference} »` : 'Photos de l’unité'} size="large" expandable expandToViewport footer={<Button variant="outline" onClick={() => setPhotoTarget(null)}>Fermer</Button>}>
                {photoTarget && <PhotoGallery entityUuid={photoTarget.id} entityType="unit" mode="multiple" onChange={() => {}} />}
            </Modal>
            {accessBuildingId && buildingById.get(accessBuildingId)?.parcelId && <AccessPointsManager open buildingId={accessBuildingId} parcelId={buildingById.get(accessBuildingId)!.parcelId} buildingName={buildingById.get(accessBuildingId)?.name ?? 'Bâtiment'} units={units} canManage={!readOnly && canDo(role, 'update_unit')} onClose={() => setAccessBuildingId(null)} />}
            {levelBuildingId && <Modal isOpen onClose={() => { setLevelBuildingId(null); setLevelEdit(null); }} title={`Niveaux · ${buildingById.get(levelBuildingId)?.name ?? 'Bâtiment'}`} size="large" footer={<Button variant="outline" onClick={() => { setLevelBuildingId(null); setLevelEdit(null); }}>Fermer</Button>}>
                <section className="access-manager">
                    <div className="access-manager__list"><h3>Niveaux déclarés</h3><p>0 = rez-de-chaussée ; nombres positifs = étages au-dessus ; nombres négatifs = sous-sols. Maximum au-dessus du rez-de-chaussée : {maximumFloorFor(levelBuildingId)}.</p>{buildingLevels.length === 0 ? <p>Aucun niveau déclaré. Vous pouvez conserver un libellé directement sur chaque unité.</p> : buildingLevels.map((level) => <article className="access-manager__item" key={level.id}><div><strong>{level.displayOrder} · {level.label}</strong><small>{level.reference}{level.description ? ` · ${level.description}` : ''}</small></div><Button variant="outline" onClick={() => editBuildingLevel(level)}>Modifier</Button></article>)}</div>
                    <form className="access-manager__form" onSubmit={(event) => void saveBuildingLevel(event)}>
                        <h3>{levelEdit ? 'Modifier un niveau' : 'Ajouter un niveau'}</h3>
                        <div className="access-manager__fields"><FormField label="Référence" htmlFor="building-level-reference" required error={levelErrors.reference}><Input id="building-level-reference" value={levelForm.reference} onChange={(event) => setLevelForm((current) => ({ ...current, reference: event.target.value }))} placeholder="Ex. : RDC" required maxLength={50} fullWidth /></FormField><FormField label="Libellé" htmlFor="building-level-label" required error={levelErrors.label}><Input id="building-level-label" value={levelForm.label} onChange={(event) => setLevelForm((current) => ({ ...current, label: event.target.value }))} placeholder="Ex. : Rez-de-chaussée" required maxLength={120} fullWidth /></FormField><FormField label="Numéro du niveau" htmlFor="building-level-order" required helpText={`0 = rez-de-chaussée ; 1 à ${maximumFloorFor(levelBuildingId)} = étages supérieurs ; −1, −2… = sous-sols.`} error={levelErrors.displayOrder}><Input id="building-level-order" type="number" min="-1000" max={maximumFloorFor(levelBuildingId)} value={levelForm.displayOrder} onChange={(event) => setLevelForm((current) => ({ ...current, displayOrder: Number(event.target.value) }))} placeholder="Ex. : 0" required fullWidth /></FormField></div>
                        <FormField label="Description (facultative)" htmlFor="building-level-description" error={levelErrors.description}><Textarea id="building-level-description" value={levelForm.description ?? ''} onChange={(event) => setLevelForm((current) => ({ ...current, description: event.target.value || null }))} placeholder="Ex. : Niveau situé à l’entrée principale du bâtiment." rows={2} maxLength={2000} fullWidth /></FormField>
                        <Button type="submit" disabled={savingLevel}>{savingLevel ? 'Enregistrement…' : levelEdit ? 'Enregistrer le niveau' : 'Ajouter le niveau'}</Button>
                    </form>
                </section>
            </Modal>}
        </Card>
    );
}
