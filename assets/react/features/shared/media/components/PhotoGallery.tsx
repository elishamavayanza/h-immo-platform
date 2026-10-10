import { useEffect, useRef, useState, type ChangeEvent } from 'react';

import { ImageEditor } from '../../../../components/UI/ImageEditor/ImageEditor';
import { Button } from '../../../../components/UI/Button';
import { Icon } from '../../../../components/UI/Icon/Icon';
import { Spinner } from '../../../../components/UI/Spinner';
import { mediaHref } from '../services/mediaService';
import { mediaService } from '../services/mediaService';
import { useToast } from '../../../../app/layout/MainLayout/contexts/ToastContext';

type PhotoGalleryMode = 'multiple' | 'single';

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
    const inputRef = useRef<HTMLInputElement>(null);
    const [editing, setEditing] = useState<string | null>(null);
    const [isUploading, setIsUploading] = useState(false);
    const [isDeleting, setIsDeleting] = useState<string | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [photos, setPhotos] = useState<string[]>(initialPhotos ?? []);

    // Charger les photos au montage si non fournies
    useEffect(() => {
        if (initialPhotos === undefined) {
            loadPhotos();
        }
    }, [entityUuid, entityType, initialPhotos]);

    const loadPhotos = async () => {
        setIsLoading(true);
        try {
            let fetchedPhotos: string[] = [];
            if (entityType === 'parcel') {
                fetchedPhotos = await mediaService.fetchParcelPhotos(entityUuid);
            } else if (entityType === 'unit') {
                fetchedPhotos = await mediaService.fetchUnitPhotos(entityUuid);
            } else {
                fetchedPhotos = await mediaService.fetchBuildingPhotos(entityUuid);
            }
            setPhotos(fetchedPhotos.map((p) => (p.startsWith('http') || p.startsWith('/') ? p : `/uploads/${p}`)));
        } catch {
            // Silencieux : on affiche juste la grille vide
        } finally {
            setIsLoading(false);
        }
    };

    const handleFile = async (event: ChangeEvent<HTMLInputElement>) => {
        if (disabled) return;
        const file = event.target.files?.[0];
        event.target.value = '';
        if (!file) return;

        if (!file.type.startsWith('image/')) {
            push('error', 'Le fichier doit être une image.');
            return;
        }
        if (file.size > 10 * 1024 * 1024) {
            push('error', 'L\'image ne doit pas dépasser 10 Mo.');
            return;
        }

        const reader = new FileReader();
        reader.onload = () => {
            if (typeof reader.result === 'string') {
                setEditing(reader.result);
            }
        };
        reader.readAsDataURL(file);
    };

    const handleApply = async (dataUrl: string, file?: File) => {
        if (!file) return;
        setIsUploading(true);
        try {
            let result;
            if (entityType === 'parcel') {
                result = await mediaService.uploadParcelPhotos(entityUuid, [file]);
            } else if (entityType === 'unit') {
                result = await mediaService.uploadUnitPhoto(entityUuid, file);
            } else {
                result = await mediaService.uploadBuildingPhotos(entityUuid, [file]);
            }
            push('success', result);
            await loadPhotos();
            onChange?.();
        } catch (cause) {
            push('error', cause instanceof Error ? cause.message : 'Échec de l\'upload.');
        } finally {
            setEditing(null);
            setIsUploading(false);
        }
    };

    const handleDelete = async (photoPath: string) => {
        if (!confirm('Retirer cette photo ?')) return;
        const filename = photoPath.split('/').pop() ?? photoPath;
        setIsDeleting(filename);
        try {
            if (entityType === 'parcel') {
                await mediaService.deleteParcelPhoto(entityUuid, filename);
            } else if (entityType === 'unit') {
                await mediaService.deleteUnitPhoto(entityUuid, filename);
            } else {
                await mediaService.deleteBuildingPhoto(entityUuid, filename);
            }
            push('success', 'Photo retirée.');
            await loadPhotos();
            onChange?.();
        } catch (cause) {
            push('error', cause instanceof Error ? cause.message : 'Échec de la suppression.');
        } finally {
            setIsDeleting(null);
        }
    };

    const isSingleMode = mode === 'single';
    const maxPhotos = isSingleMode ? 1 : 10;
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
                    <div key={`${photo}-${index}`} className="photo-gallery__item">
                        <div className="photo-gallery__preview">
                            <img src={photo} alt={`Photo ${index + 1}`} />
                        </div>
                        <div className="photo-gallery__item-actions">
                            <Button
                                type="button"
                                variant="outline"
                                size="small"
                                onClick={() => setEditing(photo)}
                                disabled={disabled || isUploading || isDeleting === photo}
                            >
                                <Icon name="edit" size={14} />
                            </Button>
                            <Button
                                type="button"
                                variant="danger"
                                size="small"
                                onClick={() => handleDelete(photo)}
                                disabled={disabled || isUploading || isDeleting === photo}
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
                    : 'Jusqu\'à 10 photos.'}
            </p>
        </div>
    );
}