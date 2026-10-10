import { useState, type FormEvent, type ChangeEvent } from 'react';

import { OrganizationSummary } from '../../../shared/components/OrganizationSummary';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { Card } from '../../../../components/UI/Card';
import { Button } from '../../../../components/UI/Button';
import { Spinner } from '../../../../components/UI/Spinner';
import { EmptyState } from '../../../../components/Data/EmptyState';
import { SearchInput } from '../../../../components/Forms/SearchInput';
import { Select } from '../../../../components/Forms/Select';
import { Input } from '../../../../components/Forms/Input';
import { Textarea } from '../../../../components/Forms/Textarea';
import { FormField } from '../../../../components/Forms/FormField';
import { Modal } from '../../../../components/UI/Modal';
import { useVitrine } from '../hooks/useVitrine';
import { VitrineTable } from '../components/VitrineTable';
import { fieldErrorMap } from '../../../shared/actionErrors';
import { ModalActions } from '../../../shared/components/ModalActions'
import type { VitrineRow } from '../types/vitrine.types';
import type { OrganizationRole } from '../../../../../services/api/api.types';
import { canDo } from '../../../shared/permissions';
import '../../../../../styles/pages/admin_immobilier/vitrine/_vitrine.scss';

const CURRENCY_OPTIONS = [
    { value: 'USD', label: 'USD' },
    { value: 'CDF', label: 'CDF' },
];

export function VitrinePage() {
    const { organizationRole } = useOrganization();
    const {
        data, rows, isLoading, error, reload,
        search, setSearch, status, setStatus,
        pendingId, togglePublished,
        updateUnit, addPhoto, removePhoto,
    } = useVitrine();

    const [editModalOpen, setEditModalOpen] = useState(false);
    const [editTarget, setEditTarget] = useState<VitrineRow | null>(null);
    const [editForm, setEditForm] = useState({
        description: '',
        monthlyRent: '',
        currency: 'USD' as 'USD' | 'CDF',
    });
    const [editFormErrors, setEditFormErrors] = useState<Record<string, string>>({});
    const [submittingEdit, setSubmittingEdit] = useState(false);

    const [photoModalOpen, setPhotoModalOpen] = useState(false);
    const [photoTarget, setPhotoTarget] = useState<VitrineRow | null>(null);
    const [photos, setPhotos] = useState<string[]>([]);
    const [uploadingPhoto, setUploadingPhoto] = useState(false);
    const [removingPhotoId, setRemovingPhotoId] = useState<string | null>(null);

    const openEdit = (target: VitrineRow) => {
        setEditTarget(target);
        setEditForm({
            description: '',
            monthlyRent: '',
            currency: 'USD',
        });
        setEditFormErrors({});
        setEditModalOpen(true);
    };

    const closeEditModal = () => {
        setEditModalOpen(false);
        setEditTarget(null);
    };

    const submitEdit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (submittingEdit || !editTarget) return;
        setSubmittingEdit(true);
        setEditFormErrors({});

        const payload = {
            description: editForm.description.trim() || undefined,
            monthlyRent: editForm.monthlyRent.trim() || undefined,
            currency: editForm.currency,
        };

        try {
            await updateUnit(editTarget.id, payload);
            closeEditModal();
        } catch (cause) {
            setEditFormErrors(fieldErrorMap(cause));
        } finally {
            setSubmittingEdit(false);
        }
    };

    const openPhotos = (target: VitrineRow) => {
        setPhotoTarget(target);
        setPhotos([]);
        setPhotoModalOpen(true);
    };

    const closePhotoModal = () => {
        setPhotoModalOpen(false);
        setPhotoTarget(null);
    };

    const handlePhotoUpload = async (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        if (!file || !photoTarget) return;
        setUploadingPhoto(true);
        try {
            await addPhoto(photoTarget.id, file);
            // Refresh would be done by hook's reload
        } catch (cause) {
            // toast already shown by hook
        } finally {
            setUploadingPhoto(false);
            event.target.value = '';
        }
    };

    const handleRemovePhoto = async (photoUuid: string) => {
        if (!photoTarget) return;
        setRemovingPhotoId(photoUuid);
        try {
            await removePhoto(photoTarget.id, photoUuid);
        } catch (cause) {
            // toast already shown by hook
        } finally {
            setRemovingPhotoId(null);
        }
    };

    let body = <VitrineTable rows={rows} role={organizationRole} pendingId={pendingId} onTogglePublished={(row) => void togglePublished(row)} onEdit={openEdit} onAddPhoto={openPhotos} />;
    if (isLoading) {
        body = <EmptyState icon={<Spinner size="large" />} title="Chargement des annonces…" description="Récupération des biens de l’organisation." />;
    } else if (error) {
        body = <EmptyState title="Annonces indisponibles" description={error} action={<Button onClick={() => void reload()}>Réessayer</Button>} />;
    } else if (rows.length === 0) {
        body = <EmptyState
            title="Aucune annonce à afficher"
            description={data && data.rows.length > 0 ? 'Aucune annonce ne correspond aux filtres.' : 'Aucune unité enregistrée pour cette organisation.'}
        />;
    }

    return <main className="organization-page organization-operational-page"><header className="organization-page__header"><div><span className="organization-page__eyebrow">PRÉSENCE EN LIGNE</span><h1>Vitrine</h1><p>Gérez la publication de vos biens sur le site public.</p></div></header>
        <OrganizationSummary items={[{ label: 'Annonces affichées', value: <>{rows.length}</> }, { label: 'Publiées', value: <>{rows.filter((row) => row.isPublished).length}</> }, { label: 'Brouillons', value: <>{rows.filter((row) => !row.isPublished).length}</> }]} />
        <Card className="organization-table-card" padding="medium"><div className="organization-table-toolbar"><div><h2>Annonces immobilières</h2><p>{data?.note ?? 'Publier une unité occupée par un bail actif est refusé.'}</p></div><div className="organization-filters organization-filters--tenant"><SearchInput value={search} onSearch={setSearch} placeholder="Rechercher une annonce…" fullWidth /><Select aria-label="Filtrer par statut" value={status} onChange={(event) => setStatus(event.target.value)} options={[{ value: 'all', label: 'Tous les statuts' }, { value: 'published', label: 'Publiées' }, { value: 'unpublished', label: 'Brouillons' }]} /></div></div>{body}</Card>
        <Modal
            isOpen={editModalOpen}
            onClose={closeEditModal}
            title={editTarget ? `Modifier « ${editTarget.title} »` : 'Modifier l\'annonce'}
            size="medium"
            footer={<ModalActions formId="edit-vitrine-form" onCancel={closeEditModal} submitLabel='Enregistrer' loadingLabel='Enregistrement…' isLoading={submittingEdit} />}
        >
            <form id="edit-vitrine-form" className="organization-management-form" onSubmit={submitEdit}>
                <FormField label="Description" htmlFor="vitrine-description" error={editFormErrors.description}>
                    <Textarea id="vitrine-description" value={editForm.description} onChange={(event) => setEditForm((current) => ({ ...current, description: event.target.value }))} rows={4} fullWidth maxLength={2000} />
                </FormField>
                <FormField label="Loyer affiché" htmlFor="vitrine-rent" error={editFormErrors.monthlyRent}>
                    <Input id="vitrine-rent" type="number" step="0.01" min="0" inputMode="decimal" value={editForm.monthlyRent} onChange={(event) => setEditForm((current) => ({ ...current, monthlyRent: event.target.value }))} placeholder="Ex. : 500.00" fullWidth />
                </FormField>
                <FormField label="Devise" htmlFor="vitrine-currency" error={editFormErrors.currency}>
                    <Select id="vitrine-currency" value={editForm.currency} onChange={(event) => setEditForm((current) => ({ ...current, currency: event.target.value as 'USD' | 'CDF' }))} options={CURRENCY_OPTIONS} />
                </FormField>
            </form>
        </Modal>
        <Modal
            isOpen={photoModalOpen}
            onClose={closePhotoModal}
            title={photoTarget ? `Photos de « ${photoTarget.title} »` : 'Gérer les photos'}
            size="large"
            footer={<><Button variant="outline" onClick={closePhotoModal} disabled={uploadingPhoto}>Fermer</Button></>}
        >
            <div className="vitrine-photo-manager">
                <div className="vitrine-photo-upload">
                    <label className="vitrine-photo-dropzone" htmlFor="photo-upload">
                        <Input id="photo-upload" type="file" accept="image/*" onChange={handlePhotoUpload} disabled={uploadingPhoto} />
                        <span>{uploadingPhoto ? 'Upload…' : 'Cliquez ou glissez une photo ici'}</span>
                    </label>
                </div>
                <div className="vitrine-photo-gallery">
                    {photos.length === 0 ? (
                        <p className="vitrine-photo-empty">Aucune photo pour cette annonce.</p>
                    ) : (
                        photos.map((photo) => (
                            <div key={photo} className="vitrine-photo-item">
                                <img src={photo} alt="Photo de l'annonce" />
                                <Button variant="danger" size="small" onClick={() => handleRemovePhoto(photo)} disabled={removingPhotoId === photo}>Retirer</Button>
                            </div>
                        ))
                    )}
                </div>
            </div>
        </Modal>
    </main>;
}