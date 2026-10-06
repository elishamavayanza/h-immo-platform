import React, { useCallback } from 'react';
import { createPortal } from 'react-dom';
import { useFloatingPosition } from '../../../hook-components/UI/PopoverMenu';

export interface SidebarItemTooltipProps {
    /** Bouton-hôte : la bulle se positionne à droite de celui-ci. */
    anchorEl: HTMLElement;
    /** Nom du menu à afficher (chaîne déjà résolue par `toTextLabel`). */
    label: string;
}

/**
 * Bulle du nom de menu affichée au survol quand le sidebar est réduit au
 * rail (desktop / tablette). Remplace l'infobulle native (`title`), dont le
 * rendu est imposé par le navigateur et ne suit pas la charte du projet.
 *
 * Portalé sur `document.body` comme le flyout : le `<aside>` du sidebar est
 * en `overflow: hidden`, un nœud resté dans le flux y serait rogné. La
 * position est pilotée par `useFloatingPosition` (moteur commun aux panneaux
 * flottants) : la bulle suit l'ancre au scroll et au redimensionnement.
 */
export function SidebarItemTooltip({ anchorEl, label }: SidebarItemTooltipProps) {
    // `getAnchorEl` doit être stable : reconstruit à chaque rendu, il
    // ferait boucler `useLayoutEffect` → `setMeasured` → rendu (page
    // blanche), exactement comme pour le flyout.
    const getAnchorEl = useCallback(() => anchorEl, [anchorEl]);

    const { floatingRef, coords, isPositioned } = useFloatingPosition<HTMLDivElement>({
        getAnchorEl,
        placement: 'right',
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
            className="sidebar-item-tooltip"
            style={style}
            role="tooltip"
            aria-hidden="true"
        >
            {label}
        </div>,
        document.body,
    );
}
