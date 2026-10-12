import { useEffect, useMemo, useRef, useState, type PointerEvent as ReactPointerEvent } from 'react';

import { Button } from '../../../../components/UI/Button';
import type { ParcelItem } from '../../shared/types/reference.types';

type Coordinates = { latitude: number; longitude: number };
type MapParcel = Omit<ParcelItem, 'latitude' | 'longitude'> & Coordinates;
type Point = { parcel: MapParcel; x: number; y: number };
type MapView = { center: Coordinates; zoom: number };

const MIN_ZOOM = 3;
const MAX_ZOOM = 19;

function project({ latitude, longitude }: Coordinates, zoom: number) {
    const scale = 256 * 2 ** zoom;
    const boundedLatitude = Math.max(-85.05112878, Math.min(85.05112878, latitude));
    const sin = Math.sin(boundedLatitude * Math.PI / 180);
    return {
        x: (longitude + 180) / 360 * scale,
        y: (0.5 - Math.log((1 + sin) / (1 - sin)) / (4 * Math.PI)) * scale,
    };
}

function unproject(x: number, y: number, zoom: number): Coordinates {
    const scale = 256 * 2 ** zoom;
    return {
        longitude: x / scale * 360 - 180,
        latitude: Math.atan(Math.sinh(Math.PI * (1 - 2 * y / scale))) * 180 / Math.PI,
    };
}

/** Calcule le cadrage qui conserve tous les repères visibles, même éloignés. */
function fitParcels(parcels: MapParcel[], width: number, height: number): MapView {
    if (parcels.length === 0) {
        return { center: { latitude: -1.6792, longitude: 29.2228 }, zoom: 13 };
    }

    const projected = parcels.map((parcel) => project(parcel, 0));
    const minX = Math.min(...projected.map((point) => point.x));
    const maxX = Math.max(...projected.map((point) => point.x));
    const minY = Math.min(...projected.map((point) => point.y));
    const maxY = Math.max(...projected.map((point) => point.y));
    const spanX = maxX - minX;
    const spanY = maxY - minY;

    if (spanX < 0.00001 && spanY < 0.00001) {
        return { center: parcels[0], zoom: 16 };
    }

    const availableWidth = Math.max(160, width - 112);
    const availableHeight = Math.max(150, height - 112);
    const scale = Math.min(availableWidth / Math.max(spanX, 0.00001), availableHeight / Math.max(spanY, 0.00001));
    const zoom = Math.max(MIN_ZOOM, Math.min(16, Math.floor(Math.log2(scale))));
    const worldScale = 2 ** zoom;
    const center = unproject(((minX + maxX) / 2) * worldScale, ((minY + maxY) / 2) * worldScale, zoom);

    return { center, zoom };
}

function clusters(points: Point[], threshold = 42) {
    const groups: Point[][] = [];
    points.forEach((point) => {
        const group = groups.find((candidate) => Math.hypot(candidate[0].x - point.x, candidate[0].y - point.y) < threshold);
        if (group) group.push(point);
        else groups.push([point]);
    });
    return groups;
}

interface ParcelMapProps {
    parcels: MapParcel[];
    initialParcelId: string | null;
    onClose: () => void;
}

/** Carte légère basée sur les tuiles OSM, sans clé externe ni dépendance cartographique. */
export function ParcelMap({ parcels, initialParcelId, onClose }: ParcelMapProps) {
    const hostRef = useRef<HTMLDivElement>(null);
    const dragRef = useRef<{ x: number; y: number; center: { x: number; y: number } } | null>(null);
    const pointersRef = useRef(new Map<number, { x: number; y: number }>());
    const pinchRef = useRef<{ distance: number; zoom: number; anchor: Coordinates; x: number; y: number } | null>(null);
    const [size, setSize] = useState({ width: 900, height: 440 });
    const [zoom, setZoom] = useState(13);
    const [center, setCenter] = useState<Coordinates>({ latitude: -1.6792, longitude: 29.2228 });
    const [selectedId, setSelectedId] = useState<string | null>(initialParcelId);
    const [numbered, setNumbered] = useState(true);

    useEffect(() => {
        const host = hostRef.current;
        if (!host) return;
        const observer = new ResizeObserver(([entry]) => setSize({ width: entry.contentRect.width, height: entry.contentRect.height }));
        observer.observe(host);
        return () => observer.disconnect();
    }, []);

    useEffect(() => {
        const view = fitParcels(parcels, size.width, size.height);
        setCenter(view.center);
        setZoom(view.zoom);
    }, [parcels, size.width, size.height]);

    const centerPixel = project(center, zoom);
    const origin = { x: centerPixel.x - size.width / 2, y: centerPixel.y - size.height / 2 };
    const points = useMemo(() => parcels.map((parcel) => {
        const pixel = project(parcel, zoom);
        return { parcel, x: pixel.x - origin.x, y: pixel.y - origin.y };
    }), [parcels, zoom, origin.x, origin.y]);
    const groups = clusters(points);
    const tileStartX = Math.floor(origin.x / 256);
    const tileStartY = Math.floor(origin.y / 256);
    const tileEndX = Math.floor((origin.x + size.width) / 256);
    const tileEndY = Math.floor((origin.y + size.height) / 256);
    const tiles = [];
    for (let x = tileStartX; x <= tileEndX; x += 1) {
        for (let y = tileStartY; y <= tileEndY; y += 1) {
            const max = 2 ** zoom;
            if (y < 0 || y >= max) continue;
            const wrappedX = ((x % max) + max) % max;
            tiles.push({ key: `${zoom}/${x}/${y}`, x: x * 256 - origin.x, y: y * 256 - origin.y, url: `https://tile.openstreetmap.org/${zoom}/${wrappedX}/${y}.png` });
        }
    }
    const selected = parcels.find((parcel) => parcel.id === selectedId) ?? null;
    const focusParcel = (parcel: MapParcel) => { setSelectedId(parcel.id); setCenter(parcel); };
    const fit = () => {
        const view = fitParcels(parcels, size.width, size.height);
        setCenter(view.center);
        setZoom(view.zoom);
    };
    const zoomAt = (nextZoom: number, x = size.width / 2, y = size.height / 2) => {
        const boundedZoom = Math.max(MIN_ZOOM, Math.min(MAX_ZOOM, nextZoom));
        if (boundedZoom === zoom) return;
        const anchor = unproject(origin.x + x, origin.y + y, zoom);
        const anchoredPixel = project(anchor, boundedZoom);
        setCenter(unproject(anchoredPixel.x - x + size.width / 2, anchoredPixel.y - y + size.height / 2, boundedZoom));
        setZoom(boundedZoom);
    };
    const distanceBetween = (first: { x: number; y: number }, second: { x: number; y: number }) => Math.hypot(first.x - second.x, first.y - second.y);
    const handlePointerDown = (event: ReactPointerEvent<HTMLDivElement>) => {
        if ((event.target as HTMLElement).closest('button')) return;
        event.currentTarget.setPointerCapture(event.pointerId);
        const rect = event.currentTarget.getBoundingClientRect();
        const position = { x: event.clientX - rect.left, y: event.clientY - rect.top };
        pointersRef.current.set(event.pointerId, position);

        if (pointersRef.current.size === 2) {
            const [first, second] = [...pointersRef.current.values()];
            const x = (first.x + second.x) / 2;
            const y = (first.y + second.y) / 2;
            pinchRef.current = {
                distance: Math.max(1, distanceBetween(first, second)),
                zoom,
                anchor: unproject(origin.x + x, origin.y + y, zoom),
                x,
                y,
            };
            dragRef.current = null;
            return;
        }

        dragRef.current = { x: position.x, y: position.y, center: centerPixel };
    };
    const handlePointerMove = (event: ReactPointerEvent<HTMLDivElement>) => {
        if (!pointersRef.current.has(event.pointerId)) return;
        const rect = event.currentTarget.getBoundingClientRect();
        pointersRef.current.set(event.pointerId, { x: event.clientX - rect.left, y: event.clientY - rect.top });

        if (pointersRef.current.size >= 2 && pinchRef.current) {
            const [first, second] = [...pointersRef.current.values()];
            const pinch = pinchRef.current;
            const nextZoom = Math.max(MIN_ZOOM, Math.min(MAX_ZOOM, Math.round(pinch.zoom + Math.log2(distanceBetween(first, second) / pinch.distance))));
            const x = (first.x + second.x) / 2;
            const y = (first.y + second.y) / 2;
            if (nextZoom !== zoom) {
                const anchoredPixel = project(pinch.anchor, nextZoom);
                setCenter(unproject(anchoredPixel.x - x + size.width / 2, anchoredPixel.y - y + size.height / 2, nextZoom));
                setZoom(nextZoom);
            } else if (x !== pinch.x || y !== pinch.y) {
                const anchoredPixel = project(pinch.anchor, zoom);
                setCenter(unproject(anchoredPixel.x - x + size.width / 2, anchoredPixel.y - y + size.height / 2, zoom));
            }
            return;
        }

        const drag = dragRef.current;
        if (!drag) return;
        const position = pointersRef.current.get(event.pointerId);
        if (position) setCenter(unproject(drag.center.x - (position.x - drag.x), drag.center.y - (position.y - drag.y), zoom));
    };
    const handlePointerEnd = (event: ReactPointerEvent<HTMLDivElement>) => {
        pointersRef.current.delete(event.pointerId);
        pinchRef.current = null;
        dragRef.current = null;
        if (pointersRef.current.size === 1) {
            const [pointer] = pointersRef.current.values();
            dragRef.current = { x: pointer.x, y: pointer.y, center: project(center, zoom) };
        }
    };

    return <div className="parcel-map" aria-label="Carte des parcelles">
        <div className="parcel-map__toolbar">
            <div><strong>{parcels.length} parcelle{parcels.length === 1 ? '' : 's'} localisée{parcels.length === 1 ? '' : 's'}</strong><span>Tous les points sont cadrés à l’ouverture. Glissez pour naviguer, pincez pour zoomer sur écran tactile.</span></div>
            <div className="parcel-map__controls" aria-label="Commandes de la carte">
                <div className="parcel-map__style" role="group" aria-label="Style des repères">
                    <button type="button" aria-pressed={numbered} onClick={() => setNumbered(true)}>Numérotés</button>
                    <button type="button" aria-pressed={!numbered} onClick={() => setNumbered(false)}>Points</button>
                </div>
                <Button variant="outline" onClick={() => zoomAt(zoom + 1)} aria-label="Zoom avant" disabled={zoom >= MAX_ZOOM}>＋</Button>
                <Button variant="outline" onClick={() => zoomAt(zoom - 1)} aria-label="Zoom arrière" disabled={zoom <= MIN_ZOOM}>−</Button>
                <Button variant="outline" onClick={fit}>Tout voir</Button>
                <Button variant="outline" onClick={onClose}>Fermer</Button>
            </div>
        </div>
        <div
            ref={hostRef}
            className="parcel-map__canvas"
            role="application"
            aria-label="Carte interactive : faites glisser pour déplacer, pincez pour zoomer sur écran tactile, utilisez la molette ou les boutons pour zoomer"
            onPointerDown={handlePointerDown}
            onPointerMove={handlePointerMove}
            onPointerUp={handlePointerEnd}
            onPointerCancel={handlePointerEnd}
            onLostPointerCapture={handlePointerEnd}
            tabIndex={0}
            onWheel={(event) => {
                event.preventDefault();
                const rect = event.currentTarget.getBoundingClientRect();
                zoomAt(zoom + (event.deltaY < 0 ? 1 : -1), event.clientX - rect.left, event.clientY - rect.top);
            }}
            onDoubleClick={(event) => {
                if ((event.target as HTMLElement).closest('button')) return;
                const rect = event.currentTarget.getBoundingClientRect();
                zoomAt(zoom + 1, event.clientX - rect.left, event.clientY - rect.top);
            }}
            onKeyDown={(event) => {
                const pan = 80;
                if (event.key === 'ArrowLeft') setCenter(unproject(centerPixel.x - pan, centerPixel.y, zoom));
                else if (event.key === 'ArrowRight') setCenter(unproject(centerPixel.x + pan, centerPixel.y, zoom));
                else if (event.key === 'ArrowUp') setCenter(unproject(centerPixel.x, centerPixel.y - pan, zoom));
                else if (event.key === 'ArrowDown') setCenter(unproject(centerPixel.x, centerPixel.y + pan, zoom));
                else if (event.key === '+' || event.key === '=') zoomAt(zoom + 1);
                else if (event.key === '-') zoomAt(zoom - 1);
                else return;
                event.preventDefault();
            }}
        >
            {tiles.map((tile) => <img key={tile.key} className="parcel-map__tile" src={tile.url} alt="" draggable={false} style={{ left: tile.x, top: tile.y }} />)}
            {groups.map((group) => {
                const x = group.reduce((sum, point) => sum + point.x, 0) / group.length;
                const y = group.reduce((sum, point) => sum + point.y, 0) / group.length;
                const isCluster = group.length > 1;
                const parcel = group[0].parcel;
                return <button key={group.map((point) => point.parcel.id).join(':')} type="button" className={`parcel-map__marker${isCluster ? ' is-cluster' : ''}${group.some((point) => point.parcel.id === selectedId) ? ' is-selected' : ''}${numbered && !isCluster ? ' is-numbered' : ''}`} style={{ left: x, top: y }} aria-label={isCluster ? `${group.length} parcelles proches, zoomer` : `Sélectionner ${parcel.name}`} onClick={() => {
                    if (isCluster) { setCenter(unproject(origin.x + x, origin.y + y, zoom)); setZoom((value) => Math.min(19, value + 2)); }
                    else focusParcel(parcel);
                }}>{isCluster ? group.length : numbered ? String(parcels.findIndex((item) => item.id === parcel.id) + 1) : ''}<span className="parcel-map__tooltip">{isCluster ? `${group.length} parcelles` : parcel.name}</span></button>;
            })}
            <div className="parcel-map__attribution">© <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noreferrer">OpenStreetMap</a> contributors</div>
        </div>
        <div className="parcel-map__details">
            <div className="parcel-map__list" aria-label="Parcelles avec coordonnées">
                {parcels.map((parcel, index) => <button key={parcel.id} type="button" className={selectedId === parcel.id ? 'is-active' : ''} onClick={() => focusParcel(parcel)}><span className="parcel-map__index">{index + 1}</span><span><strong>{parcel.name}</strong><small>{parcel.reference} · {parcel.latitude.toFixed(5)}, {parcel.longitude.toFixed(5)}</small></span></button>)}
            </div>
            {selected && <div className="parcel-map__selected"><strong>{selected.name}</strong><span>{selected.address || 'Adresse non renseignée'}</span><span>GPS : {selected.latitude.toFixed(6)}, {selected.longitude.toFixed(6)}</span><a href={`https://www.openstreetmap.org/?mlat=${selected.latitude}&mlon=${selected.longitude}#map=17/${selected.latitude}/${selected.longitude}`} target="_blank" rel="noreferrer">Ouvrir ce point dans OpenStreetMap ↗</a></div>}
        </div>
    </div>;
}
