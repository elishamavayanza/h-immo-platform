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
import { Icon } from '../../../../components/UI/Icon/Icon';
import { PopoverMenu } from '../../../../components/UI/PopoverMenu';
import type { PopoverMenuItem } from '../../../../hook-components/UI/PopoverMenu';
import type { OrganizationRole } from '../../../../../services/api/api.types';
import { formatInteger } from '../../../../../utils/format.utils';
import { canDo } from '../../../shared/permissions';
import { fieldErrorMap } from '../../../shared/utils/actionErrors';
import { ModalActions } from '../../../shared/components/ModalActions';
import { changedFields } from '../../../shared/utils/changedFields';
import { PhotoGallery } from '../../../shared/media/components/PhotoGallery';
import { PhotoViewer } from '../../../shared/media/components/PhotoViewer';
import { ParcelMap } from './ParcelMap';
import { parcelCoordinates } from './patrimoineView';
import type { CityItem, ParcelItem } from '../../shared/types/reference.types';
import type { ParcelPayload } from '../services/patrimoineService';

interface ParcelForm {
    cityUuid: string;
    reference: string;
    name: string;
    address: string;
    area: string;
    titleNumber: string;
    quarter: string;
    latitude: string;
    longitude: string;
    description: string;
}

const EMPTY_PARCEL_FORM: ParcelForm = { cityUuid: '', reference: '', name: '', address: '', area: '', titleNumber: '', quarter: '', latitude: '', longitude: '', description: '' };

function parcelToForm(parcel: ParcelItem): ParcelForm {
    return {
        cityUuid: parcel.cityId,
        reference: parcel.reference,
        name: parcel.name,
        address: parcel.address,
        area: parcel.area,
        titleNumber: parcel.titleNumber ?? '',
        quarter: parcel.quarter ?? '',
        latitude: parcel.latitude ?? '',
        longitude: parcel.longitude ?? '',
        description: parcel.description ?? '',
    };
}

interface ParcellesTabProps {
    parcels: ParcelItem[];
    cities: CityItem[];
    role: OrganizationRole | null;
    isLoading: boolean;
    error: string | null;
    note?: string | null;
    onReload: () => void;
    onCreate: (payload: ParcelPayload) => Promise<void>;
    onUpdate: (uuid: string, payload: Partial<ParcelPayload>) => Promise<void>;
    onDelete: (uuid: string) => Promise<void>;
    selectedCityId: string | null;
    onCityFilter: (uuid: string | null) => void;
    onOpen: (uuid: string) => void;
    /** Lecture seule (espace PATRON) : masque toute action d'écriture. */
    readOnly?: boolean;
}

/**
 * Onglet Parcelles : CRUD du 2ᵉ niveau du patrimoine.
 *
 * La ville est choisie à la création puis figée (le backend refuse tout
 * changement de rattachement) : à la mise à jour, le champ ville est
 * désactivé mais renvoyé à l'identique pour satisfaire le contrat
 * `ParcelRequest`. Latitude/longitude forment une paire indivisible,
 * contrôlée côté client avant l'envoi. `readOnly` masque les écritures (Patron).
 */
export function ParcellesTab({ parcels, cities, role, isLoading, error, note, onReload, onCreate, onUpdate, onDelete, selectedCityId, onCityFilter, onOpen, readOnly = false }: ParcellesTabProps) {
    const [search, setSearch] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const [editTarget, setEditTarget] = useState<ParcelItem | null>(null);
    const [form, setForm] = useState<ParcelForm>(EMPTY_PARCEL_FORM);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<ParcelItem | null>(null);
    const [mapTargetId, setMapTargetId] = useState<string | null>(null);

    const [photoModalOpen, setPhotoModalOpen] = useState(false);
    const [photoTarget, setPhotoTarget] = useState<ParcelItem | null>(null);
    const [viewTarget, setViewTarget] = useState<ParcelItem | null>(null);

    const cityById = new Map(cities.map((city) => [city.id, city]));
    const cityOptions = cities.map((city) => ({ value: city.id, label: city.name }));

    const filtered = parcels.filter((parcel) => {
        const query = search.trim().toLocaleLowerCase('fr');
        return (selectedCityId === null || parcel.cityId === selectedCityId) && (!query || [parcel.name, parcel.reference, parcel.address, cityById.get(parcel.cityId)?.name ?? ''].some((value) => value.toLocaleLowerCase('fr').includes(query)));
    });
    const mappedParcels = filtered.flatMap((parcel) => {
        const coordinates = parcelCoordinates(parcel);
        return coordinates ? [{ ...parcel, ...coordinates }] : [];
    });

    const openCreate = () => {
        setForm({ ...EMPTY_PARCEL_FORM, cityUuid: selectedCityId ?? cities[0]?.id ?? '' });
        setFormErrors({});
        setModalOpen(true);
    };

    const openEdit = (target: ParcelItem) => {
        setForm(parcelToForm(target));
        setFormErrors({});
        setEditTarget(target);
    };

    const openPhotos = (target: ParcelItem) => {
        setPhotoTarget(target);
        setPhotoModalOpen(true);
    };

    const closeModal = () => {
        setModalOpen(false);
        setEditTarget(null);
    };

    const closePhotoModal = () => {
        setPhotoModalOpen(false);
        setPhotoTarget(null);
    };

    const submit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (submitting) return;

        const hasLatitude = form.latitude.trim() !== '';
        const hasLongitude = form.longitude.trim() !== '';
        if (hasLatitude !== hasLongitude) {
            setFormErrors({ latitude: 'Renseignez la latitude et la longitude ensemble.', longitude: 'Renseignez la latitude et la longitude ensemble.' });
            return;
        }
        if (hasLatitude && (!Number.isFinite(Number(form.latitude)) || Math.abs(Number(form.latitude)) > 90
            || !Number.isFinite(Number(form.longitude)) || Math.abs(Number(form.longitude)) > 180)) {
            setFormErrors({ latitude: 'La latitude doit être comprise entre −90 et 90.', longitude: 'La longitude doit être comprise entre −180 et 180.' });
            return;
        }
        if (!form.cityUuid) {
            setFormErrors({ cityUuid: 'Sélectionnez une ville.' });
            return;
        }

        setSubmitting(true);
        setFormErrors({});
        const payload: ParcelPayload = {
            cityUuid: form.cityUuid,
            reference: form.reference.trim(),
            name: form.name.trim(),
            address: form.address.trim(),
            area: form.area.trim(),
            titleNumber: form.titleNumber.trim() || null,
            quarter: form.quarter.trim() || null,
            latitude: hasLatitude ? form.latitude.trim() : null,
            longitude: hasLongitude ? form.longitude.trim() : null,
            description: form.description.trim() || null,
        };
        try {
            if (editTarget) {
                const initial = {
                    cityUuid: editTarget.cityId,
                    reference: editTarget.reference,
                    name: editTarget.name,
                    address: editTarget.address,
                    area: editTarget.area,
                    titleNumber: editTarget.titleNumber,
                    quarter: editTarget.quarter,
                    latitude: editTarget.latitude,
                    longitude: editTarget.longitude,
                    description: editTarget.description,
                };
                const changes = changedFields(payload, initial);
                if (payload.latitude !== initial.latitude || payload.longitude !== initial.longitude) {
                    changes.latitude = payload.latitude;
                    changes.longitude = payload.longitude;
                }
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

    const columns: DataTableColumn<ParcelItem>[] = [
        {
            key: 'name',
            title: 'Parcelle',
            sortable: true,
            render: (parcel) => <div className="organization-property-name"><button type="button" className="patrimoine-link" onClick={() => onOpen(parcel.id)} aria-label={`Voir les bâtiments de ${parcel.name}`}><strong>{parcel.name}</strong></button><small>{parcel.reference} · Voir les bâtiments →</small></div>,
        },
        { key: 'city', title: 'Ville', sortable: true, render: (parcel) => <button type="button" className="patrimoine-link" onClick={() => onCityFilter(parcel.cityId)}>{cityById.get(parcel.cityId)?.name ?? '—'}</button> },
        { key: 'address', title: 'Adresse', render: (parcel) => [parcel.quarter, parcel.address].filter(Boolean).join(' · ') || '—' },
        { key: 'area', title: 'Superficie', sortable: true, render: (parcel) => `${formatInteger(Number(parcel.area))} m²` },
        { key: 'geo', title: 'Carte', render: (parcel) => {
            const hasAnyCoordinate = Boolean(parcel.latitude || parcel.longitude);
            const valid = parcelCoordinates(parcel);
            return hasAnyCoordinate
                ? valid ? <button type="button" className="patrimoine-link" onClick={() => setMapTargetId(parcel.id)} aria-label={`Afficher ${parcel.name} sur la carte`}>⌖ Voir la carte</button> : <span className="patrimoine-muted">Coordonnées invalides</span>
                : <span className="patrimoine-muted">GPS absent</span>;
        } },
        {
            key: 'actions',
            title: 'Actions',
            render: (parcel) => {
                const items: PopoverMenuItem[] = [
                    ...(canDo(role, 'view_parcel')
                        ? [{ id: 'view-photos', label: 'Voir les photos', icon: <Icon name="camera" />, onClick: () => setViewTarget(parcel) }]
                        : []),
                    ...(!readOnly && canDo(role, 'update_parcel')
                        ? [{ id: 'edit', label: 'Modifier', icon: <Icon name="edit" />, onClick: () => openEdit(parcel) }]
                        : []),
                    ...(!readOnly && canDo(role, 'update_parcel')
                        ? [{ id: 'photos', label: 'Gérer les photos', icon: <Icon name="camera" />, onClick: () => openPhotos(parcel) }]
                        : []),
                    ...(!readOnly && canDo(role, 'delete_parcel')
                        ? [{ id: 'delete', label: 'Supprimer', icon: <Icon name="trash" />, danger: true, onClick: () => setDeleteTarget(parcel) }]
                        : []),
                ];

                if (items.length === 0) return '—';

                return <PopoverMenu placement="bottom" offset={6} items={items} trigger={<span className="organization-row-actions" aria-label={`Actions pour ${parcel.name}`}><Icon name="more" /></span>} />;
            },
        },
    ];

    let body = <DataTable columns={columns} data={filtered} pageSize={12} initialSortKey="name" />;
    if (isLoading) {
        body = <EmptyState icon={<Spinner size="large" />} title="Chargement des parcelles…" description="Récupération des parcelles de l’organisation." />;
    } else if (error) {
        body = <EmptyState title="Parcelles indisponibles" description={error} action={<Button onClick={onReload}>Réessayer</Button>} />;
    } else if (filtered.length === 0) {
        body = <EmptyState
            title="Aucune parcelle"
            description={parcels.length > 0 ? 'Aucune parcelle ne correspond à cette recherche.' : 'Ajoutez une parcelle dans une ville pour structurer votre patrimoine.'}
        />;
    }

    return (
        <Card className="organization-table-card" padding="medium">
            <div className="organization-table-toolbar">
                <div><h2>Parcelles</h2><p>{note ?? 'Chaque parcelle est rattachée à une ville et porte les immeubles.'}</p></div>
                <div className="organization-filters">
                    <SearchInput value={search} onSearch={setSearch} placeholder="Rechercher une parcelle…" fullWidth />
                    <Select aria-label="Filtrer par ville" value={selectedCityId ?? 'all'} onChange={(event) => onCityFilter(event.target.value === 'all' ? null : event.target.value)} options={[
                        ...(role === 'admin_ville' ? [] : [{ value: 'all', label: 'Toutes les villes' }]),
                        ...cities.map((city) => ({ value: city.id, label: city.name })),
                    ]} />
                    {!readOnly && canDo(role, 'create_parcel') && <Button onClick={openCreate} disabled={cities.length === 0}>＋ Ajouter une parcelle</Button>}
                </div>
            </div>
            {selectedCityId && <div className="patrimoine-context"><span>Ville sélectionnée : <strong>{cityById.get(selectedCityId)?.name ?? 'Ville'}</strong></span>{role !== 'admin_ville' && <Button variant="outline" onClick={() => onCityFilter(null)}>Effacer le filtre</Button>}</div>}
            <div className="patrimoine-map-action">
                <div><strong>Repérage GPS</strong><span>{mappedParcels.length} parcelle{mappedParcels.length === 1 ? '' : 's'} sur la carte{filtered.length > mappedParcels.length ? ` · ${filtered.length - mappedParcels.length} sans coordonnées valides` : ''}</span></div>
                <Button variant="outline" onClick={() => setMapTargetId(mapTargetId ?? mappedParcels[0]?.id ?? null)} disabled={mappedParcels.length === 0}>⌖ Ouvrir la carte</Button>
            </div>
            {body}

            <Modal isOpen={mapTargetId !== null} onClose={() => setMapTargetId(null)} title="Carte des parcelles" size="large" expandable>
                <ParcelMap parcels={mappedParcels} initialParcelId={mapTargetId} onClose={() => setMapTargetId(null)} />
            </Modal>

            <Modal
                isOpen={modalOpen || editTarget !== null}
                onClose={closeModal}
                title={editTarget ? `Modifier « ${editTarget.name} »` : 'Ajouter une parcelle'}
                size="medium"
                footer={<ModalActions formId="parcel-form" onCancel={closeModal} submitLabel={editTarget ? 'Enregistrer' : 'Créer la parcelle'} loadingLabel={editTarget ? 'Enregistrement…' : 'Création…'} isLoading={submitting} />}
            >
                <form id="parcel-form" className="organization-management-form" onSubmit={submit}>
                    <FormField label="Ville" htmlFor="parcel-city" required helpText={editTarget ? 'Le rattachement à une ville n’est pas modifiable.' : undefined} error={formErrors.cityUuid}>
                        <Select id="parcel-city" value={form.cityUuid} onChange={(event) => setForm((current) => ({ ...current, cityUuid: event.target.value }))} options={cityOptions} placeholder="Sélectionner une ville" disabled={editTarget !== null} />
                    </FormField>
                    <FormField label="Référence" htmlFor="parcel-reference" required helpText="Unique par ville." error={formErrors.reference}>
                        <Input id="parcel-reference" value={form.reference} onChange={(event) => setForm((current) => ({ ...current, reference: event.target.value }))} placeholder="Ex. : PAR-001" required fullWidth maxLength={50} />
                    </FormField>
                    <FormField label="Nom" htmlFor="parcel-name" required error={formErrors.name}>
                        <Input id="parcel-name" value={form.name} onChange={(event) => setForm((current) => ({ ...current, name: event.target.value }))} placeholder="Ex. : Parcelle Katindo" required fullWidth maxLength={150} />
                    </FormField>
                    <FormField label="Adresse" htmlFor="parcel-address" required error={formErrors.address}>
                        <Input id="parcel-address" value={form.address} onChange={(event) => setForm((current) => ({ ...current, address: event.target.value }))} placeholder="Ex. : Avenue du Lac 12" required fullWidth maxLength={255} />
                    </FormField>
                    <FormField label="Quartier" htmlFor="parcel-quarter" error={formErrors.quarter}>
                        <Input id="parcel-quarter" value={form.quarter} onChange={(event) => setForm((current) => ({ ...current, quarter: event.target.value }))} placeholder="Ex. : Katindo" fullWidth maxLength={150} />
                    </FormField>
                    <FormField label="Superficie (m²)" htmlFor="parcel-area" required error={formErrors.area}>
                        <Input id="parcel-area" type="number" min="0" step="0.01" inputMode="decimal" value={form.area} onChange={(event) => setForm((current) => ({ ...current, area: event.target.value }))} placeholder="Ex. : 850" required fullWidth />
                    </FormField>
                    <FormField label="N° de titre" htmlFor="parcel-title" error={formErrors.titleNumber}>
                        <Input id="parcel-title" value={form.titleNumber} onChange={(event) => setForm((current) => ({ ...current, titleNumber: event.target.value }))} placeholder="Ex. : 1234/2024" fullWidth maxLength={100} />
                    </FormField>
                    <FormField label="Latitude" htmlFor="parcel-latitude" error={formErrors.latitude}>
                        <Input id="parcel-latitude" type="number" min="-90" max="90" step="any" inputMode="decimal" value={form.latitude} onChange={(event) => setForm((current) => ({ ...current, latitude: event.target.value }))} placeholder="Ex. : -1.6795" fullWidth />
                    </FormField>
                    <FormField label="Longitude" htmlFor="parcel-longitude" error={formErrors.longitude}>
                        <Input id="parcel-longitude" type="number" min="-180" max="180" step="any" inputMode="decimal" value={form.longitude} onChange={(event) => setForm((current) => ({ ...current, longitude: event.target.value }))} placeholder="Ex. : 29.2228" fullWidth />
                    </FormField>
                    <FormField label="Description" htmlFor="parcel-description" error={formErrors.description}>
                        <Textarea id="parcel-description" value={form.description} onChange={(event) => setForm((current) => ({ ...current, description: event.target.value }))} placeholder="Ex. : Parcelle clôturée située dans le quartier Les Volcans." rows={3} fullWidth maxLength={1000} />
                    </FormField>
                </form>
            </Modal>

            <ConfirmDialog
                isOpen={deleteTarget !== null}
                onClose={() => setDeleteTarget(null)}
                onConfirm={() => void confirmDelete()}
                title="Supprimer la parcelle"
                message={deleteTarget ? `« ${deleteTarget.name} » sera retirée de votre patrimoine. Les immeubles et unités rattachés restent conservés.` : ''}
                confirmLabel="Supprimer"
                cancelLabel="Annuler"
            />

            <Modal
                isOpen={photoModalOpen}
                onClose={closePhotoModal}
                title={photoTarget ? `Photos de « ${photoTarget.name} »` : 'Gérer les photos'}
                size="large"
                footer={<><Button variant="outline" onClick={closePhotoModal}>Fermer</Button></>}
            >
                <PhotoGallery
                    entityUuid={photoTarget?.id ?? ''}
                    entityType="parcel"
                    mode="multiple"
                    onChange={() => {}}
                />
            </Modal>

            <Modal isOpen={viewTarget !== null} onClose={() => setViewTarget(null)} title={viewTarget ? `Photos — ${viewTarget.name}` : 'Photos de la parcelle'} size="large" expandable initiallyExpanded footer={<Button variant="outline" onClick={() => setViewTarget(null)}>Fermer</Button>}>
                {viewTarget && <PhotoViewer entityUuid={viewTarget.id} entityType="parcel" entityName={viewTarget.name} />}
            </Modal>
        </Card>
    );
}
