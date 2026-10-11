import { useEffect, useMemo, useRef, useState } from 'react';

import { Button } from '../../../../components/UI/Button';
import type { ParcelItem } from '../../shared/types/reference.types';

type Coordinates = { latitude: number; longitude: number };
type MapParcel = Omit<ParcelItem, 'latitude' | 'longitude'> & Coordinates;
type Point = { parcel: MapParcel; x: number; y: number };

function project({ latitude, longitude }: Coordinates, zoom: number) {
    const scale = 256 * 2 ** zoom;
    const sin = Math.sin(latitude * Math.PI / 180);
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

    const initial = parcels.find((parcel) => parcel.id === initialParcelId) ?? parcels[0];
    useEffect(() => {
        if (initial) setCenter({ latitude: initial.latitude, longitude: initial.longitude });
    }, [initial?.latitude, initial?.longitude]);

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
        if (parcels.length === 0) return;
        const average = parcels.reduce((acc, parcel) => ({ latitude: acc.latitude + parcel.latitude / parcels.length, longitude: acc.longitude + parcel.longitude / parcels.length }), { latitude: 0, longitude: 0 });
        setCenter(average);
        setZoom(parcels.length > 1 ? 12 : 15);
    };

    return <div className="parcel-map" aria-label="Carte des parcelles">
        <div className="parcel-map__toolbar">
            <div><strong>{parcels.length} parcelle{parcels.length === 1 ? '' : 's'} localisée{parcels.length === 1 ? '' : 's'}</strong><span>Les repères proches se regroupent automatiquement. Cliquez sur un groupe pour le détailler.</span></div>
            <div className="parcel-map__controls" aria-label="Commandes de la carte">
                <div className="parcel-map__style" role="group" aria-label="Style des repères">
                    <button type="button" aria-pressed={numbered} onClick={() => setNumbered(true)}>Numérotés</button>
                    <button type="button" aria-pressed={!numbered} onClick={() => setNumbered(false)}>Points</button>
                </div>
                <Button variant="outline" onClick={() => setZoom((value) => Math.min(19, value + 1))} aria-label="Zoom avant">＋</Button>
                <Button variant="outline" onClick={() => setZoom((value) => Math.max(3, value - 1))} aria-label="Zoom arrière">−</Button>
                <Button variant="outline" onClick={fit}>Tout voir</Button>
                <Button variant="outline" onClick={onClose}>Fermer</Button>
            </div>
        </div>
        <div ref={hostRef} className="parcel-map__canvas" role="application" aria-label="Carte interactive : faites glisser pour déplacer, utilisez les commandes pour zoomer" onPointerDown={(event) => { if ((event.target as HTMLElement).closest('button')) return; dragRef.current = { x: event.clientX, y: event.clientY, center: centerPixel }; event.currentTarget.setPointerCapture(event.pointerId); }} onPointerMove={(event) => { const drag = dragRef.current; if (!drag) return; setCenter(unproject(drag.center.x - (event.clientX - drag.x), drag.center.y - (event.clientY - drag.y), zoom)); }} onPointerUp={() => { dragRef.current = null; }}>
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
