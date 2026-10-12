import { useCallback, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Link } from 'react-router-dom';
import { useFloatingPosition } from '../../../../hook-components/UI/PopoverMenu';
import type { BuildingItem, CityItem, ParcelItem, UnitItem } from '../../shared/types/reference.types';

export type PatrimoineLevel = 'overview' | 'villes' | 'parcelles' | 'batiments' | 'unites';

export interface PatrimoineContext {
    cityId: string | null;
    parcelId: string | null;
    buildingId: string | null;
}

export interface PatrimoineSelection extends PatrimoineContext {
    level: PatrimoineLevel;
}

export interface PatrimoineNavNode {
    id: string;
    label: string;
    level: PatrimoineLevel;
    to: string;
    children?: PatrimoineNavNode[];
}

export function resolvePatrimoineSelection(pathname: string, isCityAdmin: boolean): PatrimoineSelection {
    const parts = pathname.split('/').filter(Boolean);
    const valueAfter = (segment: string) => {
        const index = parts.indexOf(segment);
        return index >= 0 ? parts[index + 1] ?? null : null;
    };
    const cityId = valueAfter('villes');
    const parcelId = valueAfter('parcelles');
    const buildingId = valueAfter('batiments');
    const level: PatrimoineLevel = parts.includes('unites') || buildingId
        ? 'unites'
        : parcelId || parts.includes('batiments')
            ? 'batiments'
            : cityId
                ? 'parcelles'
                : parts.includes('parcelles')
                    ? 'parcelles'
                    : parts.includes('villes')
                        ? 'villes'
                        : isCityAdmin ? 'parcelles' : 'overview';

    return { level, cityId, parcelId, buildingId };
}

export function buildPatrimoinePath(level: PatrimoineLevel, context: PatrimoineContext): string {
    const segments = ['/app/patrimoine'];
    if (context.cityId) segments.push('villes', encodeURIComponent(context.cityId));
    if (context.parcelId) segments.push('parcelles', encodeURIComponent(context.parcelId));
    if (context.buildingId) segments.push('batiments', encodeURIComponent(context.buildingId));
    if (level === 'unites') segments.push('unites');
    if (segments.length === 1 && level !== 'overview') segments.push(level);
    return segments.join('/');
}

interface PatrimoineNavProps {
    isCityAdmin: boolean;
    selection: PatrimoineSelection;
    cities: CityItem[];
    parcels: ParcelItem[];
    buildings: BuildingItem[];
    units: UnitItem[];
    collapsed?: boolean;
}

function buildTree({ isCityAdmin, selection, cities, parcels, buildings, units }: PatrimoineNavProps): PatrimoineNavNode[] {
    const overview: PatrimoineNavNode[] = isCityAdmin ? [] : [{
        id: 'overview', label: 'Vue d’ensemble', level: 'overview',
        to: buildPatrimoinePath('overview', { cityId: null, parcelId: null, buildingId: null }),
    }];
    return [...overview, ...cities.map((city) => {
        const cityParcels = parcels.filter((parcel) => parcel.cityId === city.id);
        return {
            id: `city:${city.id}`,
            label: city.name,
            level: 'villes' as const,
            to: buildPatrimoinePath('parcelles', { cityId: city.id, parcelId: null, buildingId: null }),
            children: cityParcels.map((parcel) => {
                const parcelBuildings = buildings.filter((building) => building.parcelId === parcel.id);
                return {
                    id: `parcel:${parcel.id}`,
                    label: parcel.name || parcel.reference,
                    level: 'parcelles' as const,
                    to: buildPatrimoinePath('batiments', { cityId: city.id, parcelId: parcel.id, buildingId: null }),
                    children: parcelBuildings.map((building) => {
                        const buildingUnits = units.filter((unit) => unit.buildingId === building.id);
                        return {
                            id: `building:${building.id}`,
                            label: building.name || building.reference,
                            level: 'batiments' as const,
                            to: buildPatrimoinePath('unites', { cityId: city.id, parcelId: parcel.id, buildingId: building.id }),
                            children: buildingUnits.map((unit) => ({
                                id: `unit:${unit.id}`,
                                label: unit.reference || unit.type,
                                level: 'unites' as const,
                                to: buildPatrimoinePath('unites', { cityId: city.id, parcelId: parcel.id, buildingId: building.id }),
                            })),
                        };
                    }),
                };
            }),
        };
    })];
}

export function PatrimoineNav(props: PatrimoineNavProps) {
    const { selection } = props;
    const tree = buildTree(props);
    const [isPopoverOpen, setPopoverOpen] = useState(false);
    const [userExpanded, setUserExpanded] = useState<Set<string>>(new Set());
    const [userCollapsed, setUserCollapsed] = useState<Set<string>>(new Set());
    const anchorRef = useRef<HTMLButtonElement>(null);
    const getAnchorEl = useCallback(() => anchorRef.current, []);
    const { floatingRef, coords, isPositioned } = useFloatingPosition<HTMLDivElement>({
        getAnchorEl,
        placement: 'left',
        offset: 8,
        isOpen: isPopoverOpen,
    });
    const activeChain = new Set([
        ...(selection.cityId ? [`city:${selection.cityId}`] : []),
        ...(selection.parcelId ? [`parcel:${selection.parcelId}`] : []),
        ...(selection.buildingId ? [`building:${selection.buildingId}`] : []),
    ]);

    const renderNodes = (nodes: PatrimoineNavNode[], depth = 0) => (
        <ul className={`patrimoine-nav__list${depth ? ' patrimoine-nav__list--nested' : ''}`}>
            {nodes.map((node) => {
                const isActive = node.level === 'overview'
                    ? selection.level === 'overview'
                    : node.id === `city:${selection.cityId}` && selection.level === 'parcelles'
                        || node.id === `parcel:${selection.parcelId}` && selection.level === 'batiments'
                        || node.id === `building:${selection.buildingId}` && selection.level === 'unites';
                const hasChildren = Boolean(node.children?.length);
                const expanded = hasChildren && (userExpanded.has(node.id) || (activeChain.has(node.id) && !userCollapsed.has(node.id)));
                return (
                    <li className="patrimoine-nav__node" key={node.id}>
                        <div className={`patrimoine-nav__row patrimoine-nav__row--${node.level}${isActive ? ' patrimoine-nav__row--active' : ''}`}>
                            {hasChildren ? (
                                <button type="button" className="patrimoine-nav__disclosure" aria-label={`${expanded ? 'Replier' : 'Déplier'} ${node.label}`} aria-expanded={expanded} onClick={() => {
                                    if (expanded) {
                                        setUserExpanded((previous) => { const next = new Set(previous); next.delete(node.id); return next; });
                                        setUserCollapsed((previous) => new Set(previous).add(node.id));
                                    } else {
                                        setUserCollapsed((previous) => { const next = new Set(previous); next.delete(node.id); return next; });
                                        setUserExpanded((previous) => new Set(previous).add(node.id));
                                    }
                                }}>
                                    <span aria-hidden="true">{expanded ? '⌄' : '›'}</span>
                                </button>
                            ) : <span className="patrimoine-nav__disclosure-placeholder" aria-hidden="true" />}
                            <Link to={node.to} className="patrimoine-nav__item" aria-current={isActive ? 'page' : undefined} onClick={() => {
                                if (!props.collapsed) return;
                                setPopoverOpen(false);
                                anchorRef.current?.focus();
                            }}>
                                <span className="patrimoine-nav__icon" aria-hidden="true">{levelIcon(node.level)}</span>
                                <span className="patrimoine-nav__label">{node.label}</span>
                            </Link>
                        </div>
                        {expanded && node.children && renderNodes(node.children, depth + 1)}
                    </li>
                );
            })}
        </ul>
    );

    useEffect(() => {
        if (!isPopoverOpen) return;
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key !== 'Escape') return;
            setPopoverOpen(false);
            anchorRef.current?.focus();
        };
        const onPointerDown = (event: MouseEvent) => {
            const target = event.target as Node;
            if (floatingRef.current?.contains(target) || anchorRef.current?.contains(target)) return;
            setPopoverOpen(false);
        };
        document.addEventListener('keydown', onKeyDown);
        document.addEventListener('mousedown', onPointerDown);
        return () => {
            document.removeEventListener('keydown', onKeyDown);
            document.removeEventListener('mousedown', onPointerDown);
        };
    }, [isPopoverOpen, floatingRef]);

    if (props.collapsed) {
        const popover = isPopoverOpen && typeof document !== 'undefined' ? createPortal(
            <div ref={floatingRef} className="patrimoine-nav__popover" role="group" aria-label="Navigation du patrimoine"
                 style={{ position: 'fixed', top: coords?.top ?? 0, left: coords?.left ?? 0, visibility: isPositioned ? 'visible' : 'hidden' }}>
                <div className="patrimoine-nav__popover-title">Patrimoine</div>
                <div className="patrimoine-nav__popover-content">{renderNodes(tree)}</div>
            </div>,
            document.body,
        ) : null;
        return <div className="patrimoine-nav-rail">
            {!props.isCityAdmin && <Link className={`patrimoine-nav-rail__button${selection.level === 'overview' ? ' is-active' : ''}`} to="/app/patrimoine" aria-label="Vue d’ensemble" title="Vue d’ensemble">
                {levelIcon('overview')}
            </Link>}
            <button ref={anchorRef} type="button" className={`patrimoine-nav-rail__button${selection.level !== 'overview' ? ' is-active' : ''}`}
                    aria-label="Ouvrir la navigation du patrimoine" aria-haspopup="true" aria-expanded={isPopoverOpen} onClick={() => setPopoverOpen((open) => !open)} title="Patrimoine">
                {levelIcon('villes')}
            </button>
            {popover}
        </div>;
    }
    return <nav className="patrimoine-nav" aria-label="Arborescence du patrimoine">{renderNodes(tree)}</nav>;
}

function levelIcon(level: PatrimoineLevel) {
    const common = { viewBox: '0 0 24 24', width: 20, height: 20, fill: 'none', stroke: 'currentColor', strokeWidth: 1.8, strokeLinecap: 'round' as const, strokeLinejoin: 'round' as const };
    if (level === 'overview') return <svg {...common}><path d="M3 12h8V4H3zM13 20h8v-8h-8zM13 4h8v6h-8zM3 14h8v6H3z" /></svg>;
    if (level === 'villes') return <svg {...common}><path d="M3 21h18M5 21V8l7-5 7 5v13M9 21v-6h6v6M8 10h.01M12 10h.01M16 10h.01" /></svg>;
    if (level === 'parcelles') return <svg {...common}><path d="m3 7 9-4 9 4-9 4zM3 7v10l9 4 9-4V7M12 11v10M7.5 5 16.5 9" /></svg>;
    if (level === 'batiments') return <svg {...common}><path d="M4 21h16M6 21V4h12v17M9 8h2m2 0h2m-6 4h2m2 0h2m-6 4h2m2 0h2" /></svg>;
    return <svg {...common}><rect x="3" y="4" width="18" height="16" rx="2" /><path d="M9 4v16m6-16v16M3 12h18" /></svg>;
}
