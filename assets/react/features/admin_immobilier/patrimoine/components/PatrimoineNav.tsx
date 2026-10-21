import { Fragment, useCallback, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Link } from 'react-router-dom';
import { Icon, type IconName } from '../../../../components/UI/Icon/Icon';
import { useFloatingPosition } from '../../../../hook-components/UI/PopoverMenu';
import { SidebarItemTooltip } from '../../../../components/Navigation/Sidebar';
import type { BuildingItem, CityItem, ParcelItem, UnitItem } from '../../shared/types/reference.types';
import { canDo } from '../../../shared/permissions';
import type { OrganizationRole } from '../../../../../services/api/api.types';
import { buildPatrimoinePath, resolvePatrimoineSelection } from './patrimoineRouting';
import type { PatrimoineLevel, PatrimoineSelection } from './patrimoineRouting';

// Ré-export : le routage est isolé dans `patrimoineRouting`, mais les appelants
// (PatrimoinePage) continuent de l'importer depuis ce module.
export { buildPatrimoinePath, resolvePatrimoineSelection };
export type { PatrimoineLevel, PatrimoineContext, PatrimoineSelection } from './patrimoineRouting';

export interface PatrimoineNavNode {
    id: string;
    label: string;
    level: PatrimoineLevel;
    to: string;
    status?: string;
    children?: PatrimoineNavNode[];
}

export type PatrimoineNavAction = 'open' | 'create-child' | 'edit' | 'delete' | 'toggle-status' | 'create-city-admin';
export interface PatrimoineNavActionRequest {
    id: number;
    level: Exclude<PatrimoineLevel, 'overview'>;
    action: 'create' | 'edit' | 'delete' | 'create-city-admin';
    targetId?: string;
}

interface PatrimoineNavProps {
    isCityAdmin: boolean;
    role: OrganizationRole | null;
    readOnly?: boolean;
    onAction?: (node: PatrimoineNavNode, action: PatrimoineNavAction) => void;
    selection: PatrimoineSelection;
    cities: CityItem[];
    parcels: ParcelItem[];
    buildings: BuildingItem[];
    units: UnitItem[];
    collapsed?: boolean;
}

function buildTree({ isCityAdmin, selection, cities, parcels, buildings, units }: PatrimoineNavProps): PatrimoineNavNode[] {
    const unitNode = (unit: UnitItem, building: BuildingItem, cityId: string, parcelId: string, ancestors = new Set<string>()): PatrimoineNavNode => {
        const nextAncestors = new Set(ancestors);
        nextAncestors.add(unit.id);
        return {
            id: 'unit:' + unit.id,
            label: unit.reference || unit.type,
            level: 'unites',
            to: buildPatrimoinePath('unites', { cityId, parcelId, buildingId: building.id, unitId: unit.id }),
            children: units
                .filter((candidate) => candidate.parentUnitId === unit.id && !nextAncestors.has(candidate.id))
                .map((child) => unitNode(child, building, cityId, parcelId, nextAncestors)),
        };
    };
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
            status: city.status,
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
                        const rootUnits = buildingUnits.filter((unit) => unit.parentUnitId === null || !buildingUnits.some((candidate) => candidate.id === unit.parentUnitId));
                        return {
                            id: `building:${building.id}`,
                            label: building.name || building.reference,
                            level: 'batiments' as const,
                            to: buildPatrimoinePath('unites', { cityId: city.id, parcelId: parcel.id, buildingId: building.id }),
                            children: rootUnits.map((unit) => unitNode(unit, building, city.id, parcel.id)),
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
    const [contextMenu, setContextMenu] = useState<{ node: PatrimoineNavNode; left: number; top: number } | null>(null);
    const menuRef = useRef<HTMLDivElement>(null);
    const menuButtonRefs = useRef<HTMLButtonElement[]>([]);
    const rowActionRefs = useRef(new Map<string, HTMLButtonElement>());
    const anchorRef = useRef<HTMLButtonElement>(null);
    const getAnchorEl = useCallback(() => anchorRef.current, []);
    const { floatingRef, coords, isPositioned } = useFloatingPosition<HTMLDivElement>({
        getAnchorEl,
        placement: 'left',
        offset: 8,
        isOpen: isPopoverOpen,
    });

    // Bulle du nom des boutons du rail replié : même composant que le sidebar
    // (`SidebarItemTooltip`), pour remplacer l'infobulle native non stylable.
    // Un seul tooltip à la fois, monté après un court délai pour ne pas
    // surgir au simple passage du curseur.
    const [railTooltip, setRailTooltip] = useState<{ anchor: HTMLElement; label: string } | null>(null);
    const railTooltipTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const cancelRailTooltip = useCallback(() => {
        if (railTooltipTimer.current !== null) {
            clearTimeout(railTooltipTimer.current);
            railTooltipTimer.current = null;
        }
        setRailTooltip(null);
    }, []);
    const scheduleRailTooltip = useCallback((anchor: HTMLElement, label: string) => {
        cancelRailTooltip();
        railTooltipTimer.current = setTimeout(() => setRailTooltip({ anchor, label }), 250);
    }, [cancelRailTooltip]);
    useEffect(() => cancelRailTooltip, [cancelRailTooltip]);

    const openContextMenu = useCallback((node: PatrimoineNavNode, left: number, top: number) => {
        const width = Math.min(240, window.innerWidth - 24);
        const groups = [
            actionsFor(node).filter(({ action }) => action === 'open'),
            actionsFor(node).filter(({ action }) => action === 'create-child' || action === 'create-city-admin'),
            actionsFor(node).filter(({ action }) => action === 'edit' || action === 'delete'),
            actionsFor(node).filter(({ action }) => action === 'toggle-status'),
        ].filter((group) => group.length > 0);
        const height = Math.min(window.innerHeight - 16, 74 + actionsFor(node).length * 42 + Math.max(0, groups.length - 1) * 10);
        setContextMenu({ node, left: Math.max(8, Math.min(left, window.innerWidth - width - 8)), top: Math.max(8, Math.min(top, window.innerHeight - height - 8)) });
    }, [props.role, props.readOnly]);
    const closeContextMenu = useCallback((restoreFocus = false) => {
        const nodeId = contextMenu?.node.id;
        setContextMenu(null);
        if (restoreFocus && nodeId) requestAnimationFrame(() => rowActionRefs.current.get(nodeId)?.focus());
    }, [contextMenu?.node.id]);

    useEffect(() => {
        if (!contextMenu) return;
        const firstAction = menuRef.current?.querySelector<HTMLButtonElement>('[role="menuitem"]');
        firstAction?.focus();
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                closeContextMenu(true);
                return;
            }
            if (event.key === 'Tab') {
                event.preventDefault();
                closeContextMenu(true);
                return;
            }
            if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
            const items = menuButtonRefs.current.filter((item) => item?.isConnected);
            if (items.length === 0) return;
            event.preventDefault();
            const current = items.indexOf(document.activeElement as HTMLButtonElement);
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? items.length - 1
                : current < 0 ? (event.key === 'ArrowDown' ? 0 : items.length - 1)
                    : (current + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
            items[next]?.focus();
        };
        const onPointerDown = (event: MouseEvent) => {
            if (!menuRef.current?.contains(event.target as Node)) closeContextMenu();
        };
        document.addEventListener('keydown', onKeyDown);
        document.addEventListener('mousedown', onPointerDown);
        return () => {
            document.removeEventListener('keydown', onKeyDown);
            document.removeEventListener('mousedown', onPointerDown);
        };
    }, [contextMenu, closeContextMenu]);

    const actionsFor = (node: PatrimoineNavNode): Array<{ action: PatrimoineNavAction; label: string }> => {
        if (node.level === 'overview') return [{ action: 'open', label: 'Ouvrir la vue d’ensemble' }];
        const actions: Array<{ action: PatrimoineNavAction; label: string }> = [{ action: 'open', label: 'Ouvrir' }];
        const childAction = node.level === 'villes' ? ['create_parcel', 'Ajouter une parcelle']
            : node.level === 'parcelles' ? ['create_building', 'Ajouter un bâtiment']
                : node.level === 'batiments' ? ['create_unit', 'Ajouter une unité']
                    : node.level === 'unites' ? ['create_unit', 'Ajouter une sous-unité'] : null;
        if (!props.readOnly && childAction && canDo(props.role, childAction[0] as 'create_parcel' | 'create_building' | 'create_unit')) actions.push({ action: 'create-child', label: childAction[1] });
        const updateAction = node.level === 'villes' ? 'update_city' : node.level === 'parcelles' ? 'update_parcel' : node.level === 'batiments' ? 'update_building' : 'update_unit';
        const deleteAction = node.level === 'villes' ? 'delete_city' : node.level === 'parcelles' ? 'delete_parcel' : node.level === 'batiments' ? 'delete_building' : 'delete_unit';
        if (!props.readOnly && canDo(props.role, updateAction)) actions.push({ action: 'edit', label: 'Modifier' });
        if (!props.readOnly && canDo(props.role, deleteAction)) actions.push({ action: 'delete', label: 'Supprimer' });
        if (node.level === 'villes' && !props.readOnly && canDo(props.role, 'create_city_admin')) actions.push({ action: 'create-city-admin', label: 'Ajouter un ADMIN_VILLE' });
        if (node.level === 'villes' && !props.readOnly && canDo(props.role, node.status === 'inactive' ? 'activate_city' : 'deactivate_city')) {
            actions.push({ action: 'toggle-status', label: node.status === 'inactive' ? 'Activer la ville' : 'Désactiver la ville' });
        }
        return actions;
    };
    const actionGroupsFor = (node: PatrimoineNavNode) => {
        const actions = actionsFor(node);
        return [
            actions.filter(({ action }) => action === 'open'),
            actions.filter(({ action }) => action === 'create-child' || action === 'create-city-admin'),
            actions.filter(({ action }) => action === 'edit' || action === 'delete'),
            actions.filter(({ action }) => action === 'toggle-status'),
        ].filter((group) => group.length > 0);
    };
    const contextMenuLayer = contextMenu && typeof document !== 'undefined' ? createPortal(
        <div ref={menuRef} className="patrimoine-nav__context-menu" role="menu" aria-label={`Actions pour ${contextMenu.node.label}`} style={{ position: 'fixed', left: contextMenu.left, top: contextMenu.top }}>
            <div className="patrimoine-nav__context-menu-heading">
                <span>{contextMenu.node.level === 'villes' ? 'Ville' : contextMenu.node.level === 'parcelles' ? 'Parcelle' : contextMenu.node.level === 'batiments' ? 'Bâtiment' : 'Unité'}</span>
                <strong title={contextMenu.node.label}>{contextMenu.node.label}</strong>
            </div>
            {(() => {
                let menuItemIndex = 0;
                return actionGroupsFor(contextMenu.node).map((group, groupIndex) => <Fragment key={`group-${groupIndex}`}>
                {groupIndex > 0 && <div className="patrimoine-nav__context-menu-separator" role="separator" />}
                {group.map(({ action, label }) => {
                    const icon: IconName = action === 'open' ? 'mapPin' : action === 'create-child' ? 'plus' : action === 'edit' ? 'edit' : action === 'delete' ? 'trash' : action === 'toggle-status' ? 'power' : 'globe';
                    const itemIndex = menuItemIndex++;
                    return <button
                        key={action}
                        ref={(element) => { if (element) menuButtonRefs.current[itemIndex] = element; }}
                        type="button"
                        role="menuitem"
                        className={`patrimoine-nav__context-menu-item${action === 'delete' ? ' patrimoine-nav__context-menu-item--danger' : ''}`}
                        onClick={() => {
                            props.onAction?.(contextMenu.node, action);
                            closeContextMenu();
                        }}
                    ><Icon name={icon} size={16} /><span>{label}</span></button>;
                })}
            </Fragment>);
            })()}
        </div>,
        document.body,
    ) : null;
    const activeChain = new Set([
        ...(selection.cityId ? [`city:${selection.cityId}`] : []),
        ...(selection.parcelId ? [`parcel:${selection.parcelId}`] : []),
        ...(selection.buildingId ? [`building:${selection.buildingId}`] : []),
        ...(selection.unitId ? [`unit:${selection.unitId}`] : []),
    ]);

    const renderNodes = (nodes: PatrimoineNavNode[], depth = 0) => (
        <ul className={`patrimoine-nav__list${depth ? ' patrimoine-nav__list--nested' : ''}`}>
            {nodes.map((node) => {
                const isActive = node.level === 'overview'
                    ? selection.level === 'overview'
                    : node.id === `city:${selection.cityId}` && selection.level === 'parcelles'
                        || node.id === `parcel:${selection.parcelId}` && selection.level === 'batiments'
                        || node.id === `building:${selection.buildingId}` && selection.level === 'unites' && selection.unitId === null
                        || node.id === `unit:${selection.unitId}` && selection.unitId !== null;
                const hasChildren = Boolean(node.children?.length);
                const expanded = hasChildren && (userExpanded.has(node.id) || (activeChain.has(node.id) && !userCollapsed.has(node.id)));
                return (
                    <li className="patrimoine-nav__node" key={node.id}>
                        <div className={`patrimoine-nav__row patrimoine-nav__row--${node.level}${isActive ? ' patrimoine-nav__row--active' : ''}`} onContextMenu={(event) => {
                            event.preventDefault();
                            openContextMenu(node, event.clientX, event.clientY);
                        }}>
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
                                setContextMenu(null);
                                if (!props.collapsed) return;
                                setPopoverOpen(false);
                                anchorRef.current?.focus();
                            }}>
                                <span className="patrimoine-nav__icon" aria-hidden="true">{levelIcon(node.level)}</span>
                                <span className="patrimoine-nav__label">{node.label}</span>
                            </Link>
                            {node.level !== 'overview' && actionsFor(node).length > 1 && <button
                                ref={(element) => { if (element) rowActionRefs.current.set(node.id, element); else rowActionRefs.current.delete(node.id); }}
                                type="button"
                                className="patrimoine-nav__actions-trigger"
                                aria-label={`Actions pour ${node.label}`}
                                aria-haspopup="menu"
                                aria-expanded={contextMenu?.node.id === node.id}
                                onClick={(event) => {
                                    const bounds = event.currentTarget.getBoundingClientRect();
                                    openContextMenu(node, bounds.right - Math.min(240, window.innerWidth - 24), bounds.bottom + 4);
                                }}
                                onContextMenu={(event) => { event.preventDefault(); openContextMenu(node, event.clientX, event.clientY); }}
                            >⋯</button>}
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
        return <><div className="patrimoine-nav-rail">
            {!props.isCityAdmin && <Link className={`patrimoine-nav-rail__button${selection.level === 'overview' ? ' is-active' : ''}`} to="/app/patrimoine" aria-label="Vue d’ensemble"
                    onMouseEnter={(event) => scheduleRailTooltip(event.currentTarget, 'Vue d’ensemble')}
                    onMouseLeave={cancelRailTooltip}
                    onFocus={(event) => scheduleRailTooltip(event.currentTarget, 'Vue d’ensemble')}
                    onBlur={cancelRailTooltip}>
                {levelIcon('overview')}
            </Link>}
            <button ref={anchorRef} type="button" className={`patrimoine-nav-rail__button${selection.level !== 'overview' ? ' is-active' : ''}`}
                    aria-label="Ouvrir la navigation du patrimoine" aria-haspopup="true" aria-expanded={isPopoverOpen}
                    onClick={() => { setPopoverOpen((open) => !open); cancelRailTooltip(); }}
                    onMouseEnter={(event) => scheduleRailTooltip(event.currentTarget, 'Patrimoine')}
                    onMouseLeave={cancelRailTooltip}
                    onFocus={(event) => scheduleRailTooltip(event.currentTarget, 'Patrimoine')}
                    onBlur={cancelRailTooltip}>
                {levelIcon('villes')}
            </button>
            {railTooltip !== null && <SidebarItemTooltip anchorEl={railTooltip.anchor} label={railTooltip.label} placement="left" />}
            {popover}
        </div>{contextMenuLayer}</>;
    }
    return <><nav className="patrimoine-nav" aria-label="Arborescence du patrimoine">{renderNodes(tree)}</nav>{contextMenuLayer}</>;
}

function levelIcon(level: PatrimoineLevel) {
    const common = { viewBox: '0 0 24 24', width: 20, height: 20, fill: 'none', stroke: 'currentColor', strokeWidth: 1.8, strokeLinecap: 'round' as const, strokeLinejoin: 'round' as const };
    if (level === 'overview') return <svg {...common}><path d="M3 12h8V4H3zM13 20h8v-8h-8zM13 4h8v6h-8zM3 14h8v6H3z" /></svg>;
    if (level === 'villes') return <svg {...common}><path d="M3 21h18M5 21V8l7-5 7 5v13M9 21v-6h6v6M8 10h.01M12 10h.01M16 10h.01" /></svg>;
    if (level === 'parcelles') return <svg {...common}><path d="m3 7 9-4 9 4-9 4zM3 7v10l9 4 9-4V7M12 11v10M7.5 5 16.5 9" /></svg>;
    if (level === 'batiments') return <svg {...common}><path d="M4 21h16M6 21V4h12v17M9 8h2m2 0h2m-6 4h2m2 0h2m-6 4h2m2 0h2" /></svg>;
    return <svg {...common}><rect x="3" y="4" width="18" height="16" rx="2" /><path d="M9 4v16m6-16v16M3 12h18" /></svg>;
}
