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
import { formatInteger } from '../../../../../utils/format.utils';
import { canDo } from '../../../shared/permissions';
import { fieldErrorMap } from '../../../shared/actionErrors';
import { ModalActions } from '../../../shared/components/ModalActions';
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
    onUpdate: (uuid: string, payload: ParcelPayload) => Promise<void>;
    onDelete: (uuid: string) => Promise<void>;
    onAddPhotos: (parcelUuid: string, files: File[]) => Promise<void>;
    onDeletePhoto: (parcelUuid: string, filename: string) => Promise<void>;
}

/**
 * Onglet Parcelles : CRUD du 2ᵉ niveau du patrimoine.
 *
 * La ville est choisie à la création puis figée (le backend refuse tout
 * changement de rattachement) : à la mise à jour, le champ ville est
 * désactivé mais renvoyé à l'identique pour satisfaire le contrat
 * `ParcelRequest`. Latitude/longitude forment une paire indivisible,
 * contrôlée côté client avant l'envoi.
 */
export function ParcellesTab({ parcels, cities, role, isLoading, error, note, onReload, onCreate, onUpdate, onDelete, onAddPhotos, onDeletePhoto }: ParcellesTabProps) {
    const [search, setSearch] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const [editTarget, setEditTarget] = useState<ParcelItem | null>(null);
    const [form, setForm] = useState<ParcelForm>(EMPTY_PARCEL_FORM);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<ParcelItem | null>(null);

    const [photoModalOpen, setPhotoModalOpen] = useState(false);
    const [photoTarget, setPhotoTarget] = useState<ParcelItem | null>(null);
    const [uploadingPhoto, setUploadingPhoto] = useState(false);
    const [removingPhotoId, setRemovingPhotoId] = useState<string | null>(null);

    const cityById = new Map(cities.map((city) => [city.id, city]));
    const cityOptions = cities.map((city) => ({ value: city.id, label: city.name }));

    const filtered = parcels.filter((parcel) => {
        const query = search.trim().toLocaleLowerCase('fr');
        return !query || [parcel.name, parcel.reference, parcel.address, cityById.get(parcel.cityId)?.name ?? ''].some((value) => value.toLocaleLowerCase('fr').includes(query));
    });

    const openCreate = () => {
        setForm({ ...EMPTY_PARCEL_FORM, cityUuid: cities[0]?.id ?? '' });
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

    const handlePhotoUpload = async (event: React.ChangeEvent<HTMLInputElement>) => {
        const files = event.target.files;
        if (!files || !photoTarget) return;
        setUploadingPhoto(true);
        try {
            await onAddPhotos(photoTarget.id, Array.from(files));
        } catch (cause) {
            // toast already shown by hook
        } finally {
            setUploadingPhoto(false);
            event.target.value = '';
        }
    };

    const handleRemovePhoto = async (filename: string) => {
        if (!photoTarget) return;
        setRemovingPhotoId(filename);
        try {
            await onDeletePhoto(photoTarget.id, filename);
        } catch (cause) {
            // toast already shown by hook
        } finally {
            setRemovingPhotoId(null);
        }
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

    const columns: DataTableColumn<ParcelItem>[] = [
        {
            key: 'name',
            title: 'Parcelle',
            sortable: true,
            render: (parcel) => <div className="organization-property-name"><strong>{parcel.name}</strong><small>{parcel.reference}</small></div>,
        },
        { key: 'city', title: 'Ville', sortable: true, render: (parcel) => cityById.get(parcel.cityId)?.name ?? '—' },
        { key: 'address', title: 'Adresse', render: (parcel) => [parcel.quarter, parcel.address].filter(Boolean).join(' · ') || '—' },
        { key: 'area', title: 'Superficie', sortable: true, render: (parcel) => `${formatInteger(Number(parcel.area))} m²` },
        { key: 'geo', title: 'GPS', render: (parcel) => (parcel.latitude && parcel.longitude ? <Badge variant="secondary">Localisée</Badge> : '—') },
        {
            key: 'actions',
            title: 'Actions',
            render: (parcel) => {
                const items: PopoverMenuItem[] = [
                    ...(canDo(role, 'update_parcel')
                        ? [{ id: 'edit', label: 'Modifier', icon: <span aria-hidden="true">✎</span>, onClick: () => openEdit(parcel) }]
                        : []),
                    ...(canDo(role, 'create_parcel')
                        ? [{ id: 'photos', label: 'Gérer les photos', icon: <span aria-hidden="true">📷</span>, onClick: () => openPhotos(parcel) }]
                        : []),
                    ...(canDo(role, 'delete_parcel')
                        ? [{ id: 'delete', label: 'Supprimer', icon: <span aria-hidden="true">🗑</span>, danger: true, onClick: () => setDeleteTarget(parcel) }]
                        : []),
                ];

                if (items.length === 0) return '—';

                return <PopoverMenu placement="bottom" offset={6} items={items} trigger={<span className="organization-row-actions" aria-label={`Actions pour ${parcel.name}`}><span aria-hidden="true">•••</span></span>} />;
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
                    <span />
                    {canDo(role, 'create_parcel') && <Button onClick={openCreate} disabled={cities.length === 0}>＋ Ajouter une parcelle</Button>}
                </div>
            </div>
            {body}

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
                        <Input id="parcel-latitude" type="number" step="any" inputMode="decimal" value={form.latitude} onChange={(event) => setForm((current) => ({ ...current, latitude: event.target.value }))} placeholder="Ex. : -1.6795" fullWidth />
                    </FormField>
                    <FormField label="Longitude" htmlFor="parcel-longitude" error={formErrors.longitude}>
                        <Input id="parcel-longitude" type="number" step="any" inputMode="decimal" value={form.longitude} onChange={(event) => setForm((current) => ({ ...current, longitude: event.target.value }))} placeholder="Ex. : 29.2228" fullWidth />
                    </FormField>
                    <FormField label="Description" htmlFor="parcel-description" error={formErrors.description}>
                        <Textarea id="parcel-description" value={form.description} onChange={(event) => setForm((current) => ({ ...current, description: event.target.value }))} rows={3} fullWidth maxLength={1000} />
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
                footer={<><Button variant="outline" onClick={closePhotoModal} disabled={uploadingPhoto}>Fermer</Button></>}
            >
                <div className="patrimoine-photo-manager">
                    <div className="patrimoine-photo-upload">
                        <label className="patrimoine-photo-dropzone" htmlFor="parcel-photo-upload">
                            <Input id="parcel-photo-upload" type="file" accept="image/*" multiple onChange={handlePhotoUpload} disabled={uploadingPhoto} />
                            <span>{uploadingPhoto ? 'Upload…' : 'Cliquez ou glissez des photos ici'}</span>
                        </label>
                    </div>
                    <div className="patrimoine-photo-gallery">
                        <p className="patrimoine-photo-empty">Aucune photo pour cette parcelle.</p>
                    </div>
                </div>
            </Modal>
        </Card>
    );
}
