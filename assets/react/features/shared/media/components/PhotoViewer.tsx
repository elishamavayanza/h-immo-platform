import { useEffect, useState } from 'react';

import { Button } from '../../../../components/UI/Button';
import { EmptyState } from '../../../../components/Data/EmptyState';
import { Spinner } from '../../../../components/UI/Spinner';
import { mediaService } from '../services/mediaService';

interface PhotoViewerProps {
    entityUuid: string;
    entityType: 'parcel' | 'building';
    entityName: string;
}

/** Galerie en lecture seule, distincte des contrôles de gestion des fichiers. */
export function PhotoViewer({ entityUuid, entityType, entityName }: PhotoViewerProps) {
    const [photos, setPhotos] = useState<string[]>([]);
    const [activeIndex, setActiveIndex] = useState<number | null>(null);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [reloadKey, setReloadKey] = useState(0);

    useEffect(() => {
        let active = true;
        setIsLoading(true);
        setError(null);
        const load = entityType === 'parcel'
            ? mediaService.fetchParcelPhotos(entityUuid)
            : mediaService.fetchBuildingPhotos(entityUuid);
        void load.then((paths) => {
            if (active) setPhotos(paths.map((path) => path.startsWith('http') || path.startsWith('/') ? path : `/uploads/${path}`));
        }).catch((cause: unknown) => {
            if (active) setError(cause instanceof Error ? cause.message : 'Impossible de charger les photos.');
        }).finally(() => {
            if (active) setIsLoading(false);
        });
        return () => { active = false; };
    }, [entityUuid, entityType, reloadKey]);

    const closePreview = () => setActiveIndex(null);
    const nextPhoto = (direction: -1 | 1) => {
        if (activeIndex === null || photos.length === 0) return;
        setActiveIndex((activeIndex + direction + photos.length) % photos.length);
    };

    if (isLoading) return <EmptyState icon={<Spinner size="large" />} title="Chargement des photos…" description={`Récupération des photos de ${entityName}.`} />;
    if (error) return <EmptyState title="Photos indisponibles" description={error} action={<Button onClick={() => setReloadKey((value) => value + 1)}>Réessayer</Button>} />;
    if (photos.length === 0) return <EmptyState title="Aucune photo pour le moment" description={`Les photos ajoutées à « ${entityName} » apparaîtront ici.`} />;

    if (activeIndex !== null) return <div className="photo-viewer__preview" aria-label={`Photo ${activeIndex + 1} sur ${photos.length}`}>
        <div className="photo-viewer__preview-toolbar">
            <Button variant="outline" onClick={closePreview}>← Retour à la galerie</Button>
            <span>{activeIndex + 1} / {photos.length}</span>
            <div><Button variant="outline" onClick={() => nextPhoto(-1)} aria-label="Photo précédente">‹</Button><Button variant="outline" onClick={() => nextPhoto(1)} aria-label="Photo suivante">›</Button></div>
        </div>
        <img src={photos[activeIndex]} alt={`${entityName} — photo ${activeIndex + 1}`} />
    </div>;

    return <section className="photo-viewer" aria-label={`Galerie photo de ${entityName}`}>
        <div className="photo-viewer__heading"><strong>{photos.length} photo{photos.length === 1 ? '' : 's'}</strong><span>Sélectionnez une image pour l’agrandir.</span></div>
        <div className="photo-viewer__grid">
            {photos.map((photo, index) => <button key={`${photo}-${index}`} type="button" className="photo-viewer__tile" onClick={() => setActiveIndex(index)} aria-label={`Afficher la photo ${index + 1} en grand`}>
                <img src={photo} alt={`${entityName} — photo ${index + 1}`} loading="lazy" />
                <span>Photo {index + 1}</span>
            </button>)}
        </div>
    </section>;
}
