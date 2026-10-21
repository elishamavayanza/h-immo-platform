import { useEffect, useRef, useState, type ChangeEvent } from 'react';

import { ImageEditor } from '../../../../components/UI/ImageEditor/ImageEditor';
import { Button } from '../../../../components/UI/Button';
import { Icon } from '../../../../components/UI/Icon/Icon';
import { Spinner } from '../../../../components/UI/Spinner';
import { ConfirmDialog } from '../../../../components/UI/ConfirmDialog';
import { mediaService } from '../services/mediaService';
import { useToast } from '../../../../app/layout/MainLayout/contexts/ToastContext';
import '../../../../../styles/components/UI/PhotoGallery.scss';

type PhotoGalleryMode = 'multiple' | 'single';
interface PhotoItem { url: string; deleteId: string; }
interface EditingPhoto { source: string; replacing: PhotoItem | null; }

interface PhotoGalleryProps {
    /** ID de l'entité (parcelle, unité, bâtiment, etc.) */
    entityUuid: string;
    /** Type d'entité pour l'API */
    entityType: 'parcel' | 'unit' | 'building';
    /** Photos actuelles (URLs ou chemins) - optionnel, sera chargé si non fourni */
    photos?: string[];
    /** Mode : multiple = galerie (parcelles), single = photo unique (unités) */
    mode: PhotoGalleryMode;
    /** Désactiver l'upload/suppression */
    disabled?: boolean;
    /** Callback après changement (optionnel, pour rafraîchir la liste parente) */
    onChange?: () => void;
}

export function PhotoGallery({ entityUuid, entityType, photos: initialPhotos, mode, disabled, onChange }: PhotoGalleryProps) {
    const { push } = useToast();
    const entityDestination = entityType === 'parcel' ? 'à la parcelle' : entityType === 'unit' ? 'à l’unité' : 'au bâtiment';
    const entitySource = entityType === 'parcel' ? 'de la parcelle' : entityType === 'unit' ? 'de l’unité' : 'du bâtiment';
    const inputRef = useRef<HTMLInputElement>(null);
    const [editing, setEditing] = useState<EditingPhoto | null>(null);
    const [isUploading, setIsUploading] = useState(false);
    const [isDeleting, setIsDeleting] = useState<string | null>(null);
    const [isRestoring, setIsRestoring] = useState<string | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [pendingDelete, setPendingDelete] = useState<PhotoItem | null>(null);
    const [photos, setPhotos] = useState<PhotoItem[]>((initialPhotos ?? []).map((url) => ({ url, deleteId: url.split('/').pop() ?? url })));
    const [removedUnitPhotos, setRemovedUnitPhotos] = useState<PhotoItem[]>([]);

    // Charger les photos au montage si non fournies
    useEffect(() => {
        if (initialPhotos === undefined) {
            loadPhotos();
        }
    }, [entityUuid, entityType, initialPhotos]);

    const loadPhotos = async () => {
        setIsLoading(true);
        try {
            let fetchedPhotos: PhotoItem[] = [];
            if (entityType === 'parcel') {
                setRemovedUnitPhotos([]);
                fetchedPhotos = (await mediaService.fetchParcelPhotos(entityUuid)).map((path) => ({ url: path, deleteId: path.split('/').pop() ?? path }));
            } else if (entityType === 'unit') {
                const [active, removed] = await Promise.all([
                    mediaService.fetchUnitPhotos(entityUuid),
                    mediaService.fetchDeletedUnitPhotos(entityUuid),
                ]);
                fetchedPhotos = active.map((photo) => ({ url: photo.url, deleteId: photo.uuid }));
                setRemovedUnitPhotos(removed.map((photo) => ({ url: photo.url, deleteId: photo.uuid })));
            } else {
                setRemovedUnitPhotos([]);
                fetchedPhotos = (await mediaService.fetchBuildingPhotos(entityUuid)).map((path) => ({ url: path, deleteId: path.split('/').pop() ?? path }));
            }
            setPhotos(fetchedPhotos.map((photo) => ({ ...photo, url: photo.url.startsWith('http') || photo.url.startsWith('/') ? photo.url : `/uploads/${photo.url}` })));
        } catch {
            // Silencieux : on affiche juste la grille vide
        } finally {
            setIsLoading(false);
        }
    };

    const restorePhoto = async (photo: PhotoItem) => {
        if (entityType !== 'unit') return;
        setIsRestoring(photo.deleteId);
        try {
            await mediaService.restoreUnitPhoto(entityUuid, photo.deleteId);
            push('success', 'La photo a été restaurée dans la galerie de l’unité.');
            await loadPhotos();
            onChange?.();
        } catch (cause) {
            push('error', cause instanceof Error ? cause.message : 'La photo n’a pas pu être restaurée.');
        } finally {
            setIsRestoring(null);
        }
    };

    const handleFile = async (event: ChangeEvent<HTMLInputElement>) => {
        if (disabled) return;
        const files = Array.from(event.target.files ?? []);
        event.target.value = '';
        if (files.length === 0) return;

        for (const file of files) {
            if (!file.type.startsWith('image/')) {
                push('error', 'Le fichier doit être une image.');
                return;
            }
            if (file.size > 10 * 1024 * 1024) {
                push('error', 'L\'image ne doit pas dépasser 10 Mo.');
                return;
            }
        }

        // Pour le mode single, on ne prend que le premier fichier
        if (isSingleMode) {
            const reader = new FileReader();
            reader.onload = () => {
                if (typeof reader.result === 'string') {
                    setEditing({ source: reader.result, replacing: isSingleMode ? photos[0] ?? null : null });
                }
            };
            reader.readAsDataURL(files[0]);
        } else {
            // Pour le mode multiple, on upload directement tous les fichiers
            setIsUploading(true);
            try {
                let uploadedCount = files.length;
                if (entityType === 'parcel') {
                    await mediaService.uploadParcelPhotos(entityUuid, files);
                } else if (entityType === 'unit') {
                    const selected = files.slice(0, maxPhotos - photos.length);
                    uploadedCount = selected.length;
                    for (const file of selected) {
                        await mediaService.uploadUnitPhoto(entityUuid, file);
                    }
                } else {
                    await mediaService.uploadBuildingPhotos(entityUuid, files);
                }
                push('success', `${uploadedCount} photo${uploadedCount > 1 ? 's ont été ajoutées' : ' a été ajoutée'} ${entityDestination}.`);
                await loadPhotos();
                onChange?.();
            } catch (cause) {
                push('error', cause instanceof Error ? cause.message : 'Échec de l\'upload.');
            } finally {
                setIsUploading(false);
            }
        }
    };

    const removePhoto = async (photo: PhotoItem) => {
        if (entityType === 'parcel') {
            await mediaService.deleteParcelPhoto(entityUuid, photo.deleteId);
        } else if (entityType === 'unit') {
            await mediaService.deleteUnitPhoto(entityUuid, photo.deleteId);
        } else {
            await mediaService.deleteBuildingPhoto(entityUuid, photo.deleteId);
        }
    };

    const uploadOnePhoto = async (file: File, knownUnitPhotoIds?: Set<string>): Promise<PhotoItem> => {
        if (entityType === 'parcel') {
            const [uploaded] = await mediaService.uploadParcelPhotos(entityUuid, [file]);
            if (!uploaded) throw new Error('Le serveur n’a pas confirmé l’ajout de la photo.');
            return { url: uploaded.url, deleteId: uploaded.path.split('/').pop() ?? uploaded.path };
        }
        if (entityType === 'building') {
            const [uploaded] = await mediaService.uploadBuildingPhotos(entityUuid, [file]);
            if (!uploaded) throw new Error('Le serveur n’a pas confirmé l’ajout de la photo.');
            return { url: uploaded.url, deleteId: uploaded.path.split('/').pop() ?? uploaded.path };
        }

        const before = knownUnitPhotoIds ?? new Set((await mediaService.fetchUnitPhotos(entityUuid)).map((photo) => photo.uuid));
        await mediaService.uploadUnitPhoto(entityUuid, file);
        const added = (await mediaService.fetchUnitPhotos(entityUuid)).find((photo) => !before.has(photo.uuid));
        if (!added) throw new Error('La nouvelle photo a été envoyée, mais son identifiant n’a pas pu être confirmé. Actualisez la galerie avant de réessayer.');
        return { url: added.url, deleteId: added.uuid };
    };

    const handleApply = async (_dataUrl: string, file?: File) => {
        if (!file) return;
        setIsUploading(true);
        try {
            if (editing?.replacing) {
                const beforeUnitPhotos = entityType === 'unit'
                    ? new Set((await mediaService.fetchUnitPhotos(entityUuid)).map((photo) => photo.uuid))
                    : undefined;
                const replacement = await uploadOnePhoto(file, beforeUnitPhotos);
                try {
                    await removePhoto(editing.replacing);
                } catch (cause) {
                    try {
                        await removePhoto(replacement);
                    } catch {
                        throw new Error('La nouvelle photo a été ajoutée, mais le remplacement n’a pas pu être finalisé. Vérifiez la galerie avant de recommencer.');
                    }
                    throw cause;
                }
                push('success', `La photo a été remplacée ${entityDestination}.`);
            } else {
                await uploadOnePhoto(file);
                push('success', `La photo « ${file.name} » a été ajoutée ${entityDestination}.`);
            }
            await loadPhotos();
            onChange?.();
        } catch (cause) {
            push('error', cause instanceof Error ? cause.message : 'Échec de l\'upload.');
        } finally {
            setEditing(null);
            setIsUploading(false);
        }
    };

    const handleDelete = async (photo: PhotoItem) => {
        setIsDeleting(photo.deleteId);
        try {
            if (entityType === 'parcel') {
                await mediaService.deleteParcelPhoto(entityUuid, photo.deleteId);
            } else if (entityType === 'unit') {
                await mediaService.deleteUnitPhoto(entityUuid, photo.deleteId);
            } else {
                await mediaService.deleteBuildingPhoto(entityUuid, photo.deleteId);
            }
            const photoName = photo.url.split('/').pop() ?? 'photo';
            push('success', `La photo « ${photoName} » a été retirée ${entitySource}.`);
            await loadPhotos();
            onChange?.();
        } catch (cause) {
            push('error', cause instanceof Error ? cause.message : 'Échec de la suppression.');
        } finally {
            setIsDeleting(null);
        }
    };

    const isSingleMode = mode === 'single';
    const maxPhotos = entityType === 'unit' ? 12 : isSingleMode ? 1 : 10;
    const canAdd = photos.length < maxPhotos && !disabled;

    const handleFileClick = () => {
        if (canAdd) inputRef.current?.click();
    };

    // En mode édition (ImageEditor ouvert)
    if (editing) {
        return (
            <div className="photo-gallery">
                <p className="photo-gallery__hint">
                    Glissez la photo pour ajuster le cadrage, zoomez ou pivotez, puis validez.
                </p>
                <ImageEditor
                    src={editing.source}
                    shape="square"
                    outputSize={400}
                    onCancel={() => setEditing(null)}
                    onApply={(dataUrl, file) => void handleApply(dataUrl, file)}
                />
            </div>
        );
    }

    if (isLoading) {
        return (
            <div className="photo-gallery">
                <div className="photo-gallery__loading">
                    <Spinner size="medium" />
                    <span>Chargement des photos…</span>
                </div>
            </div>
        );
    }

    return (
        <div className="photo-gallery">
            <div className="photo-gallery__grid" role="region" aria-label="Galerie de photos : zone défilante" tabIndex={0}>
                {photos.map((photo, index) => (
                    <div key={`${photo.deleteId}-${index}`} className="photo-gallery__item">
                        <div className="photo-gallery__preview">
                            <img src={photo.url} alt={`Photo ${index + 1}`} loading="lazy" />
                        </div>
                        <div className="photo-gallery__item-actions">
                            <Button
                                type="button"
                                variant="outline"
                                size="small"
                                onClick={() => setEditing({ source: photo.url, replacing: photo })}
                                disabled={disabled || isUploading || isDeleting === photo.deleteId}
                                aria-label={`Recadrer et remplacer la photo ${index + 1}`}
                                title="Recadrer et remplacer"
                            >
                                <Icon name="edit" size={14} />
                            </Button>
                            <Button
                                type="button"
                                variant="danger"
                                size="small"
                                onClick={() => setPendingDelete(photo)}
                                disabled={disabled || isUploading || isDeleting === photo.deleteId}
                                aria-label={`Supprimer la photo ${index + 1}`}
                                title="Supprimer"
                            >
                                <Icon name="trash" size={14} />
                            </Button>
                        </div>
                    </div>
                ))}
                {canAdd && (
                    <div className="photo-gallery__item photo-gallery__item--add">
                        <div className="photo-gallery__preview photo-gallery__preview--add">
                            <Icon name="plus" size={32} />
                        </div>
                        <div className="photo-gallery__item-actions">
                            <input
                                ref={inputRef}
                                type="file"
                                accept="image/png,image/jpeg,image/webp,image/gif"
                                hidden
                                onChange={handleFile}
                                disabled={disabled}
                                multiple={!isSingleMode}
                            />
                            <Button
                                type="button"
                                variant="outline"
                                onClick={handleFileClick}
                                disabled={disabled || isUploading}
                            >
                                {isSingleMode ? 'Ajouter la photo' : 'Ajouter des photos'}
                            </Button>
                        </div>
                    </div>
                )}
            </div>
            {entityType === 'unit' && removedUnitPhotos.length > 0 && <section className="photo-gallery__removed" aria-label="Photos retirées de la galerie">
                <h3>Photos retirées</h3>
                <p className="photo-gallery__hint">Ces photos restent récupérables. La restauration est refusée si la galerie a atteint sa limite.</p>
                <div className="photo-gallery__grid" role="region" aria-label="Photos retirées : zone défilante" tabIndex={0}>
                    {removedUnitPhotos.map((photo, index) => <div key={photo.deleteId} className="photo-gallery__item">
                        <div className="photo-gallery__preview"><img src={photo.url.startsWith('http') || photo.url.startsWith('/') ? photo.url : `/uploads/${photo.url}`} alt={`Photo retirée ${index + 1}`} loading="lazy" /></div>
                        <div className="photo-gallery__item-actions"><Button type="button" variant="outline" size="small" onClick={() => void restorePhoto(photo)} disabled={disabled || isUploading || isDeleting !== null || isRestoring !== null} isLoading={isRestoring === photo.deleteId}>Restaurer</Button></div>
                    </div>)}
                </div>
            </section>}
            <p className="photo-gallery__hint">
                {isSingleMode
                    ? 'Une seule photo. Cliquez pour la remplacer.'
                    : `Jusqu'à ${maxPhotos} photos.`}
            </p>
            <ConfirmDialog
                isOpen={pendingDelete !== null}
                onClose={() => setPendingDelete(null)}
                onCancel={() => setPendingDelete(null)}
                onConfirm={() => {
                    if (pendingDelete) void handleDelete(pendingDelete);
                    setPendingDelete(null);
                }}
                title="Retirer cette photo ?"
                message={entityType === 'unit' ? 'Cette photo sera retirée de la galerie. Vous pourrez la restaurer depuis la section « Photos retirées ».' : 'Cette photo sera retirée de la galerie.'}
                confirmLabel="Retirer"
            />
        </div>
    );
}
