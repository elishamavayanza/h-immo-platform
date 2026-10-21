import React, { useCallback } from 'react';
import { createPortal } from 'react-dom';
import { useFloatingPosition } from '../../../hook-components/UI/PopoverMenu';

export interface SidebarItemTooltipProps {
    /** Élément-hôte auquel la bulle est attachée. */
    anchorEl: HTMLElement;
    /** Nom du menu à afficher (chaîne déjà résolue par `toTextLabel`). */
    label: string;
    /**
     * Position souhaitée. Le moteur peut choisir le côté opposé si le
     * viewport manque de place ; la flèche suit alors le côté retenu.
     */
    placement?: 'right' | 'left' | 'top' | 'bottom';
}

/**
 * Bulle de libellé partagée par les menus latéraux compacts et les tabs.
 * Remplace l'infobulle native (`title`), dont le rendu est imposé par le
 * navigateur et ne suit pas la charte du projet.
 *
 * Portalé sur `document.body` comme le flyout : le `<aside>` du sidebar est
 * en `overflow: hidden`, un nœud resté dans le flux y serait rogné. La
 * position est pilotée par `useFloatingPosition` (moteur commun aux panneaux
 * flottants) : la bulle suit l'ancre au scroll et au redimensionnement.
 */
export function SidebarItemTooltip({ anchorEl, label, placement = 'right' }: SidebarItemTooltipProps) {
    // `getAnchorEl` doit être stable : reconstruit à chaque rendu, il
    // ferait boucler `useLayoutEffect` → `setMeasured` → rendu (page
    // blanche), exactement comme pour le flyout.
    const getAnchorEl = useCallback(() => anchorEl, [anchorEl]);

    const { floatingRef, coords, isPositioned, resolvedPlacement } = useFloatingPosition<HTMLDivElement>({
        getAnchorEl,
        placement,
        offset: 8,
        isOpen: true,
    });

    const style: React.CSSProperties = {
        position: 'fixed',
        top: coords?.top ?? 0,
        left: coords?.left ?? 0,
        visibility: isPositioned ? 'visible' : 'hidden',
    };

    // `aria-hidden` : le bouton porte déjà le nom via `aria-label` (le
    // libellé est masqué `display: none` en rail). Annoncer la bulle en
    // plus ferait lire le nom deux fois — même choix que le titre du flyout.
    return createPortal(
        <div
            ref={floatingRef}
            className={`sidebar-item-tooltip sidebar-item-tooltip--${resolvedPlacement}`}
            style={style}
            role="tooltip"
            aria-hidden="true"
        >
            {label}
        </div>,
        document.body,
    );
}
