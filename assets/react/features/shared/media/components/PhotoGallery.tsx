import { useEffect, useRef, useState, type ChangeEvent } from 'react';

import { ImageEditor } from '../../../../components/UI/ImageEditor/ImageEditor';
import { Button } from '../../../../components/UI/Button';
import { Icon } from '../../../../components/UI/Icon/Icon';
import { Spinner } from '../../../../components/UI/Spinner';
import { mediaService } from '../services/mediaService';
import { useToast } from '../../../../app/layout/MainLayout/contexts/ToastContext';

type PhotoGalleryMode = 'multiple' | 'single';
interface PhotoItem { url: string; deleteId: string; }

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
    const [editing, setEditing] = useState<string | null>(null);
    const [isUploading, setIsUploading] = useState(false);
    const [isDeleting, setIsDeleting] = useState<string | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [photos, setPhotos] = useState<PhotoItem[]>((initialPhotos ?? []).map((url) => ({ url, deleteId: url.split('/').pop() ?? url })));

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
                fetchedPhotos = (await mediaService.fetchParcelPhotos(entityUuid)).map((path) => ({ url: path, deleteId: path.split('/').pop() ?? path }));
            } else if (entityType === 'unit') {
                fetchedPhotos = (await mediaService.fetchUnitPhotos(entityUuid)).map((photo) => ({ url: photo.url, deleteId: photo.uuid }));
            } else {
                fetchedPhotos = (await mediaService.fetchBuildingPhotos(entityUuid)).map((path) => ({ url: path, deleteId: path.split('/').pop() ?? path }));
            }
            setPhotos(fetchedPhotos.map((photo) => ({ ...photo, url: photo.url.startsWith('http') || photo.url.startsWith('/') ? photo.url : `/uploads/${photo.url}` })));
        } catch {
            // Silencieux : on affiche juste la grille vide
        } finally {
            setIsLoading(false);
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
                    setEditing(reader.result);
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

    const handleApply = async (dataUrl: string, file?: File) => {
        if (!file) return;
        setIsUploading(true);
        try {
            if (entityType === 'parcel') {
                await mediaService.uploadParcelPhotos(entityUuid, [file]);
            } else if (entityType === 'unit') {
                await mediaService.uploadUnitPhoto(entityUuid, file);
            } else {
                await mediaService.uploadBuildingPhotos(entityUuid, [file]);
            }
            push('success', `La photo « ${file.name} » a été ajoutée ${entityDestination}.`);
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
        if (!confirm('Retirer cette photo ?')) return;
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
                    src={editing}
                    shape="square"
                    outputSize={400}
                    onCancel={() => setEditing(null)}
                    onApply={handleApply}
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
            <div className="photo-gallery__grid">
                {photos.map((photo, index) => (
                    <div key={`${photo.deleteId}-${index}`} className="photo-gallery__item">
                        <div className="photo-gallery__preview">
                            <img src={photo.url} alt={`Photo ${index + 1}`} />
                        </div>
                        <div className="photo-gallery__item-actions">
                            <Button
                                type="button"
                                variant="outline"
                                size="small"
                                onClick={() => setEditing(photo.url)}
                                disabled={disabled || isUploading || isDeleting === photo.deleteId}
                            >
                                <Icon name="edit" size={14} />
                            </Button>
                            <Button
                                type="button"
                                variant="danger"
                                size="small"
                                onClick={() => handleDelete(photo)}
                                disabled={disabled || isUploading || isDeleting === photo.deleteId}
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
            <p className="photo-gallery__hint">
                {isSingleMode
                    ? 'Une seule photo. Cliquez pour la remplacer.'
                    : `Jusqu'à ${maxPhotos} photos.`}
            </p>
        </div>
    );
}
