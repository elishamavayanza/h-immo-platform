import { useState, useRef, useEffect, useCallback, useLayoutEffect } from 'react';

export type FloatingPlacement = 'top' | 'bottom' | 'left' | 'right';

/** Position d'un panneau flottant, en pixels, déjà bornée dans le viewport. */
export interface FloatingCoords {
    top: number;
    left: number;
}

export interface UseFloatingPositionProps {
    /**
     * Rend l'élément servant d'ancre, lu AU MOMENT de la mesure.
     *
     * Une fonction plutôt qu'une ref : l'ancre n'est pas toujours montée au
     * moment où le panneau s'ouvre (un sous-menu ancré sur une ligne du
     * sidebar n'existe qu'après son rendu), et il est remplacé quand la
     * largeur du sidebar change. Lire l'ancre à la mesure évite tout
     * couplage entre l'ordre de montage et celui des effets.
     */
    getAnchorEl: () => HTMLElement | null;
    placement?: FloatingPlacement;
    offset?: number;
    isOpen: boolean;
}

/** Marge minimale entre le panneau et les bords du viewport. */
const MARGIN = 8;

const OPPOSITE_PLACEMENT: Record<FloatingPlacement, FloatingPlacement> = {
    top: 'bottom',
    bottom: 'top',
    left: 'right',
    right: 'left',
};

const clamp = (value: number, min: number, max: number): number =>
    Math.min(Math.max(value, min), max);

/**
 * Positionnement flottant : mesure, FLIP, clamp dans le viewport, et
 * re-mesure sur scroll / resize / changement de taille de l'ancre.
 *
 * Ce hook ne porte QUE la géométrie. Il ne décide ni de l'ouverture, ni du
 * clic extérieur, ni de l'Echap : chaque composant garde la main sur son
 * état, et ce hook ne fait que dire où le panneau doit se peindre. Il est
 * partagé par <PopoverMenu /> (menu utilisateur) et le flyout de sous-menu
 * du sidebar, afin qu'un seul moteur Calcule les positions — deux
 * implémentations du même calcul divergeraient au premier viewport étroit.
 *
 * La position est rendue dans `coords` en PIXELS FINAUX, jamais en
 * `transform` : un `translate(-50%, -100%)` mal ajusté suffisait à sortir du
 * viewport sans qu'aucune règle ne puisse le rattraper.
 */
export function useFloatingPosition<T extends HTMLElement = HTMLDivElement>({
    getAnchorEl,
    placement = 'bottom',
    offset = 8,
    isOpen,
}: UseFloatingPositionProps) {
    // `null` tant que le panneau n'a pas été mesuré : on le garde alors
    // invisible plutôt que de le peindre en (0, 0), ce qui le faisait
    // apparaître à moitié hors écran pendant une frame.
    const [measured, setMeasured] = useState<{ coords: FloatingCoords; placement: FloatingPlacement } | null>(null);
    const floatingRef = useRef<T | null>(null);

    const computePosition = useCallback((): { coords: FloatingCoords; placement: FloatingPlacement } | null => {
        const anchorEl = getAnchorEl();
        const panelEl = floatingRef.current;
        if (!anchorEl || !panelEl) return null;

        const anchorRect = anchorEl.getBoundingClientRect();
        const panelWidth = panelEl.offsetWidth;
        const panelHeight = panelEl.offsetHeight;
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;

        const room = {
            top: anchorRect.top,
            bottom: viewportHeight - anchorRect.bottom,
            left: anchorRect.left,
            right: viewportWidth - anchorRect.right,
        };

        const fits = (candidate: FloatingPlacement): boolean => {
            switch (candidate) {
                case 'top':
                    return room.top >= panelHeight + offset + MARGIN;
                case 'bottom':
                    return room.bottom >= panelHeight + offset + MARGIN;
                case 'left':
                    return room.left >= panelWidth + offset + MARGIN;
                case 'right':
                    return room.right >= panelWidth + offset + MARGIN;
                default:
                    return false;
            }
        };

        // FLIP : le placement demandé est inversé si la place manque. Place
        // insuffisante des deux côtés, on garde le placement demandé et c'est
        // le clamp final qui limite le débordement.
        let resolved = placement;
        if (!fits(resolved) && fits(OPPOSITE_PLACEMENT[resolved])) {
            resolved = OPPOSITE_PLACEMENT[resolved];
        }

        let top: number;
        let left: number;

        switch (resolved) {
            case 'top':
                top = room.top - offset - panelHeight;
                left = anchorRect.left + anchorRect.width / 2 - panelWidth / 2;
                break;
            case 'bottom':
                top = anchorRect.bottom + offset;
                left = anchorRect.left + anchorRect.width / 2 - panelWidth / 2;
                break;
            case 'left':
                top = anchorRect.top + anchorRect.height / 2 - panelHeight / 2;
                left = anchorRect.left - offset - panelWidth;
                break;
            default:
                top = anchorRect.top + anchorRect.height / 2 - panelHeight / 2;
                left = anchorRect.right + offset;
                break;
        }

        return {
            coords: {
                top: clamp(top, MARGIN, Math.max(MARGIN, viewportHeight - panelHeight - MARGIN)),
                left: clamp(left, MARGIN, Math.max(MARGIN, viewportWidth - panelWidth - MARGIN)),
            },
            placement: resolved,
        };
    }, [getAnchorEl, placement, offset]);

    const reposition = useCallback(() => {
        if (!isOpen) return;
        setMeasured(computePosition());
    }, [isOpen, computePosition]);

    // Mesure puis placement : `useLayoutEffect` garantit que le panneau est
    // dans le DOM (donc mesurable) avant le premier paint.
    useLayoutEffect(() => {
        if (!isOpen) {
            setMeasured(null);

            return;
        }

        setMeasured(computePosition());
    }, [isOpen, computePosition]);

    // Le panneau suit son ancre : sans cela il resterait figé à sa position
    // d'ouverture et finirait hors du viewport dès que la page défilait, que
    // la fenêtre changeait de taille, ou que le sidebar se repliait.
    useEffect(() => {
        if (!isOpen) return;

        window.addEventListener('resize', reposition);
        window.addEventListener('scroll', reposition, true);

        const anchorEl = getAnchorEl();
        const observer = typeof ResizeObserver === 'undefined' || !anchorEl
            ? null
            : new ResizeObserver(reposition);
        if (observer && anchorEl) observer.observe(anchorEl);

        return () => {
            window.removeEventListener('resize', reposition);
            window.removeEventListener('scroll', reposition, true);
            observer?.disconnect();
        };
    }, [isOpen, reposition, getAnchorEl]);

    return {
        floatingRef,
        coords: measured?.coords ?? null,
        /** Placement RÉELLEMENT retenu, qui peut être l'inverse de celui demandé. */
        resolvedPlacement: measured?.placement ?? placement,
        /** Faux tant que la première mesure n'a pas eu lieu. */
        isPositioned: measured !== null,
        offset,
        reposition,
    };
}
