import { useEffect, useRef, useState, type PointerEvent as ReactPointerEvent } from 'react';

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
    const [zoom, setZoom] = useState(1);
    const [stageAspectRatio, setStageAspectRatio] = useState<number | null>(null);
    const [offset, setOffset] = useState({ x: 0, y: 0 });
    const stageRef = useRef<HTMLDivElement>(null);
    const zoomRef = useRef(zoom);
    zoomRef.current = zoom;
    const dragRef = useRef<{ pointerId: number; x: number; y: number; offsetX: number; offsetY: number; mode: 'pan' | 'swipe' } | null>(null);
    const pointersRef = useRef(new Map<number, { x: number; y: number }>());
    const pinchRef = useRef<{ distance: number; zoom: number } | null>(null);
    const multiTouchRef = useRef(false);

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

    const closePreview = () => { setActiveIndex(null); setZoom(1); setOffset({ x: 0, y: 0 }); };
    const nextPhoto = (direction: -1 | 1) => {
        if (activeIndex === null || photos.length === 0) return;
        setActiveIndex((activeIndex + direction + photos.length) % photos.length);
        setZoom(1);
        setOffset({ x: 0, y: 0 });
        setStageAspectRatio(null);
    };

    useEffect(() => {
        if (activeIndex === null) return;
        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'ArrowLeft') {
                event.preventDefault();
                setActiveIndex((current) => current === null ? null : (current - 1 + photos.length) % photos.length);
                setZoom(1);
                setOffset({ x: 0, y: 0 });
            } else if (event.key === 'ArrowRight') {
                event.preventDefault();
                setActiveIndex((current) => current === null ? null : (current + 1) % photos.length);
                setZoom(1);
                setOffset({ x: 0, y: 0 });
            }
        };
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [activeIndex, photos.length]);

    const clampOffset = (x: number, y: number, scale = zoom) => {
        const stage = stageRef.current;
        const image = stage?.querySelector('img');
        if (!stage || !image?.naturalWidth || !image.naturalHeight) return { x, y };
        const bounds = stage.getBoundingClientRect();
        const fit = Math.min(bounds.width / image.naturalWidth, bounds.height / image.naturalHeight);
        const maxX = Math.max(0, (image.naturalWidth * fit * scale - bounds.width) / 2);
        const maxY = Math.max(0, (image.naturalHeight * fit * scale - bounds.height) / 2);
        return { x: Math.max(-maxX, Math.min(maxX, x)), y: Math.max(-maxY, Math.min(maxY, y)) };
    };
    const changeZoom = (next: number) => {
        const bounded = Math.max(1, Math.min(5, next));
        setZoom(bounded);
        setOffset((current) => bounded === 1 ? { x: 0, y: 0 } : clampOffset(current.x, current.y, bounded));
    };
    useEffect(() => {
        const stage = stageRef.current;
        if (activeIndex === null || !stage) return;

        const handleWheel = (event: WheelEvent) => {
            event.preventDefault();
            const nextZoom = Math.max(1, Math.min(5, zoomRef.current + (event.deltaY < 0 ? 0.25 : -0.25)));
            zoomRef.current = nextZoom;
            setZoom(nextZoom);
            setOffset((current) => nextZoom === 1 ? { x: 0, y: 0 } : clampOffset(current.x, current.y, nextZoom));
        };

        stage.addEventListener('wheel', handleWheel, { passive: false });
        return () => stage.removeEventListener('wheel', handleWheel);
    }, [activeIndex]);
    const startDrag = (event: ReactPointerEvent<HTMLDivElement>) => {
        event.currentTarget.setPointerCapture(event.pointerId);
        pointersRef.current.set(event.pointerId, { x: event.clientX, y: event.clientY });
        if (pointersRef.current.size === 2) {
            const [first, second] = [...pointersRef.current.values()];
            pinchRef.current = { distance: Math.hypot(second.x - first.x, second.y - first.y), zoom };
            multiTouchRef.current = true;
            dragRef.current = null;
            return;
        }
        if (pointersRef.current.size > 2) return;
        dragRef.current = {
            pointerId: event.pointerId,
            x: event.clientX,
            y: event.clientY,
            offsetX: offset.x,
            offsetY: offset.y,
            mode: zoom > 1 ? 'pan' : 'swipe',
        };
    };
    const moveDrag = (event: ReactPointerEvent<HTMLDivElement>) => {
        if (pointersRef.current.has(event.pointerId)) {
            pointersRef.current.set(event.pointerId, { x: event.clientX, y: event.clientY });
        }
        if (pinchRef.current && pointersRef.current.size >= 2) {
            const [first, second] = [...pointersRef.current.values()];
            const nextZoom = Math.max(1, Math.min(5, pinchRef.current.zoom * Math.hypot(second.x - first.x, second.y - first.y) / Math.max(1, pinchRef.current.distance)));
            setZoom(nextZoom);
            setOffset((current) => nextZoom === 1 ? { x: 0, y: 0 } : clampOffset(current.x, current.y, nextZoom));
            return;
        }
        const drag = dragRef.current;
        if (!drag || drag.pointerId !== event.pointerId || drag.mode !== 'pan') return;
        setOffset(clampOffset(drag.offsetX + event.clientX - drag.x, drag.offsetY + event.clientY - drag.y));
    };
    const stopDrag = (event: ReactPointerEvent<HTMLDivElement>, allowSwipe = true) => {
        const hadMultiplePointers = multiTouchRef.current;
        pointersRef.current.delete(event.pointerId);
        if (pointersRef.current.size < 2) pinchRef.current = null;
        if (pointersRef.current.size === 0) multiTouchRef.current = false;
        const drag = dragRef.current;
        if (!drag || drag.pointerId !== event.pointerId) return;
        dragRef.current = null;
        const deltaX = event.clientX - drag.x;
        const deltaY = event.clientY - drag.y;
        if (allowSwipe && !hadMultiplePointers && drag.mode === 'swipe' && Math.abs(deltaX) >= 48 && Math.abs(deltaX) > Math.abs(deltaY) * 1.2) {
            nextPhoto(deltaX < 0 ? 1 : -1);
        }
    };
    if (isLoading) return <EmptyState icon={<Spinner size="large" />} title="Chargement des photos…" description={`Récupération des photos de ${entityName}.`} />;
    if (error) return <EmptyState title="Photos indisponibles" description={error} action={<Button onClick={() => setReloadKey((value) => value + 1)}>Réessayer</Button>} />;
    if (photos.length === 0) return <EmptyState title="Aucune photo pour le moment" description={`Les photos ajoutées à « ${entityName} » apparaîtront ici.`} />;

    if (activeIndex !== null) return <div className="photo-viewer__preview" aria-label={`Photo ${activeIndex + 1} sur ${photos.length}`}>
        <div className="photo-viewer__preview-toolbar">
            <div className="photo-viewer__preview-navigation">
                <Button variant="outline" onClick={closePreview}>← Retour à la galerie</Button>
                <span>{activeIndex + 1} / {photos.length}</span>
            </div>
            <div className="photo-viewer__zoom-controls" aria-label="Zoom de la photo">
                <Button variant="outline" onClick={() => changeZoom(zoom - 0.5)} aria-label="Dézoomer" disabled={zoom <= 1}>−</Button>
                <span>{Math.round(zoom * 100)} %</span>
                <Button variant="outline" onClick={() => changeZoom(zoom + 0.5)} aria-label="Zoomer" disabled={zoom >= 5}>＋</Button>
                <Button variant="outline" onClick={() => changeZoom(1)} disabled={zoom === 1}>Réinitialiser</Button>
            </div>
            <div className="photo-viewer__step-controls"><Button variant="outline" onClick={() => nextPhoto(-1)} aria-label="Photo précédente">‹</Button><Button variant="outline" onClick={() => nextPhoto(1)} aria-label="Photo suivante">›</Button></div>
        </div>
        <span className="photo-viewer__touch-hint">Pincez pour zoomer ; glissez pour déplacer l’image ou passer à la photo suivante.</span>
        <div ref={stageRef} className={`photo-viewer__stage${zoom > 1 ? ' is-zoomed' : ' is-swipeable'}`} style={{ aspectRatio: stageAspectRatio ? String(stageAspectRatio) : '4 / 3' }} onPointerDown={startDrag} onPointerMove={moveDrag} onPointerUp={stopDrag} onPointerCancel={(event) => stopDrag(event, false)} onLostPointerCapture={(event) => stopDrag(event, false)}>
            <img src={photos[activeIndex]} alt={`${entityName} — photo ${activeIndex + 1}`} draggable={false} onLoad={(event) => setStageAspectRatio(event.currentTarget.naturalWidth / event.currentTarget.naturalHeight)} style={{ transform: `translate(${offset.x}px, ${offset.y}px) scale(${zoom})` }} />
        </div>
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
