import React, { useEffect, useCallback } from 'react';
import { createPortal } from 'react-dom';
import { useFloatingPosition } from '../../../hook-components/UI/PopoverMenu';
import { SidebarItem, SidebarSubItem } from '../../../hook-components/Navigation/Sidebar';
import { FLYOUT_OFFSET } from './flyout.constants';

export interface SidebarFlyoutProps {
    /** Parent dont les sous-items sont affichés dans le panneau. */
    parent: SidebarItem;
    /** Élément DOM du bouton parent : le panneau s'ancre dessus. */
    anchorEl: HTMLElement | null;
    /** `aria-current="page"` et le style actif suivent cet identifiant. */
    activeId?: string;
    onSelect: (item: SidebarItem | SidebarSubItem) => void;
    onClose: () => void;
    /** Annule la fermeture différée pendant le survol du panneau. */
    onPointerEnter: () => void;
    /** Relance la fermeture différée quand le pointeur quitte le panneau. */
    onPointerLeave: () => void;
}

/**
     * Panneau de sous-menu du sidebar replié (rail desktop et tablette).
     *
     * Rendu dans un portal sur `document.body` : le `<aside>` est en
     * `position: fixed` avec un contexte d'empilement propre et un `overflow`
     * contraint, si bien qu'un panneau placé dans le flux serait rogné ou
     * recouvert.
     *
     * Le contenu est dupliqué depuis la section en ligne du sidebar, et non
     * déplacé : les deux ne peuvent pas coexister (le flyout n'existe que
     * quand les libellés sont masqués), mais le faire ainsi garde
     * `aria-controls` et `aria-expanded` du parent pointant sur l'élément
     * réellement affiché.
     */
export function SidebarFlyout({
                                 parent,
                                 anchorEl,
                                 activeId,
                                 onSelect,
                                 onClose,
                                 onPointerEnter,
                                 onPointerLeave,
                             }: SidebarFlyoutProps) {
    const panelId = `sidebar-flyout-${parent.id}`;
    const textLabel = typeof parent.label === 'string' ? parent.label : '';

    // `getAnchorEl` doit être stable : s'il change à chaque rendu,
    // `useFloatingPosition` recalcule la position en boucle (effet → setState → rendu).
    const getAnchorEl = useCallback(() => anchorEl, [anchorEl]);

    // L'ancre est relue À CHAQUE mesure plutôt que capturée dans un état : le
    // panneau peut s'ouvrir après un redimensionnement qui a déjà déplacé le
    // bouton, et une ancre figée le peindrait hors du viewport.
    const { floatingRef, coords, isPositioned } = useFloatingPosition<HTMLDivElement>({
        getAnchorEl,
        placement: 'right',
        offset: FLYOUT_OFFSET,
        isOpen: true,
    });

    const style: React.CSSProperties = {
        position: 'fixed',
        top: coords?.top ?? 0,
        left: coords?.left ?? 0,
        visibility: isPositioned ? 'visible' : 'hidden',
    };

    const isChildActive = (child: SidebarSubItem): boolean =>
        child.active === true || (activeId !== undefined && child.id === activeId);

    // Focus rendu au bouton parent à la fermeture. Déclaré ici parce que la
    // fermeture est un effet : le bouton vit dans le sidebar et le panneau est
    // portalé ailleurs. Sans ce rendu, le focus resterait sur un nœud retiré du
    // DOM après le clic, et le Tab repartirait du début de la page.
    useEffect(() => {
        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.key !== 'Escape') return;

            onClose();
            anchorEl?.focus();
        };

        const handlePointerDown = (event: MouseEvent) => {
            const target = event.target as Node;

            // Clic dans le panneau ou sur son ancre : ni l'un ni l'autre ne
            // referme. Le bouton parent s'en charge lui-même (bascule), donc le
            // vérifier ici évite de le refermer juste avant qu'il ne se
            // rouvre.
            if (floatingRef.current?.contains(target) || anchorEl?.contains(target)) return;

            onClose();
        };

        document.addEventListener('keydown', handleKeyDown);
        document.addEventListener('mousedown', handlePointerDown);

        return () => {
            document.removeEventListener('keydown', handleKeyDown);
            document.removeEventListener('mousedown', handlePointerDown);
        };
    }, [anchorEl, onClose, floatingRef]);

    const panel = (
        <div
            ref={floatingRef}
            id={panelId}
            className="sidebar-flyout"
            style={style}
            role="group"
            aria-label={textLabel}
            onMouseEnter={onPointerEnter}
            onMouseLeave={onPointerLeave}
        >
            {/* Titre non interactif : il aligne visuellement le panneau sur
                son parent. `aria-hidden` car le nom du panneau est déjà porté
                par `aria-label`, et le doublon le ferait lire deux fois. */}
            <div className="sidebar-flyout__title" aria-hidden="true">
                {parent.label}
            </div>
            <div className="sidebar-flyout__items">
                {parent.children?.map((child) => {
                    const active = isChildActive(child);

                    return (
                        <button
                            key={child.id}
                            type="button"
                            className={`sidebar-flyout__item ${active ? 'sidebar-flyout__item--active' : ''}`}
                            onClick={() => onSelect(child)}
                            disabled={child.disabled}
                            aria-current={active ? 'page' : undefined}
                        >
                            {child.icon && <span className="sidebar-flyout__icon">{child.icon}</span>}
                            <span className="sidebar-flyout__label">{child.label}</span>
                            {child.badge !== undefined && (
                                <span className="sidebar-flyout__badge">{child.badge}</span>
                            )}
                        </button>
                    );
                })}
            </div>
        </div>
    );

    return createPortal(panel, document.body);
}
