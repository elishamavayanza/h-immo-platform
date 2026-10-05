import { useState, useRef, useEffect, useCallback, useLayoutEffect } from 'react';

export type PopoverPlacement = 'top' | 'bottom' | 'left' | 'right';

export interface PopoverMenuItem {
    id: string;
    label: React.ReactNode;
    icon?: React.ReactNode;
    onClick?: () => void;
    disabled?: boolean;
    danger?: boolean;
    separator?: boolean;
}

export interface UsePopoverMenuProps {
    items: PopoverMenuItem[];
    placement?: PopoverPlacement;
    offset?: number;
    closeOnClickItem?: boolean;
    closeOnOutsideClick?: boolean;
    closeOnEscape?: boolean;
    className?: string;
}

const MARGIN = 8; // marge minimale par rapport aux bords du viewport

/** Position du menu, en pixels, déjà contrainte dans le viewport. */
interface PopoverCoords {
    top: number;
    left: number;
}

const OPPOSITE_PLACEMENT: Record<PopoverPlacement, PopoverPlacement> = {
    top: 'bottom',
    bottom: 'top',
    left: 'right',
    right: 'left',
};

const clamp = (value: number, min: number, max: number): number =>
    Math.min(Math.max(value, min), max);

export function usePopoverMenu({
                                    items,
                                    placement = 'bottom',
                                    offset = 8,
                                    closeOnClickItem = true,
                                    closeOnOutsideClick = true,
                                    closeOnEscape = true,
                                    className = '',
                                }: UsePopoverMenuProps) {
    const [isOpen, setIsOpen] = useState(false);
    // `null` tant que le menu n'a pas été mesuré : on le garde alors
    // invisible plutôt que de le peindre en (0, 0), ce qui le faisait
    // apparaître à moitié hors écran pendant une frame.
    const [position, setPosition] = useState<{ coords: PopoverCoords; placement: PopoverPlacement } | null>(null);
    const triggerRef = useRef<HTMLDivElement>(null);
    const menuRef = useRef<HTMLDivElement>(null);

    /**
     * Place le menu en pixels autour du déclencheur.
     *
     * Deux différences avec l'ancien calcul (transform + correction a
     * posteriori), toutes deux responsables du menu « coupé » :
     *   1. le placement demandé est FLIPPIÉ si la place manque (un menu
     *      au-dessus du footer du sidebar n'avait pas la place et se
     *      faisait pushed vers le bas, donc sous le bord de l'écran) ;
     *   2. `top`/`left` sont des pixels finaux, bornés par les dimensions
     *      réelles du menu. Avant, un `translate(-50%, -100%)` mal ajusté
     *      suffisait à sortir du viewport sans qu'aucune règle ne puisse
     *      le rattraper.
     */
    const computePosition = useCallback((): { coords: PopoverCoords; placement: PopoverPlacement } | null => {
        const triggerEl = triggerRef.current;
        const menuEl = menuRef.current;
        if (!triggerEl || !menuEl) return null;

        const triggerRect = triggerEl.getBoundingClientRect();
        const menuWidth = menuEl.offsetWidth;
        const menuHeight = menuEl.offsetHeight;
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;

        const room = {
            top: triggerRect.top,
            bottom: viewportHeight - triggerRect.bottom,
            left: triggerRect.left,
            right: viewportWidth - triggerRect.right,
        };

        const fits = (candidate: PopoverPlacement): boolean => {
            switch (candidate) {
                case 'top':
                    return room.top >= menuHeight + offset + MARGIN;
                case 'bottom':
                    return room.bottom >= menuHeight + offset + MARGIN;
                case 'left':
                    return room.left >= menuWidth + offset + MARGIN;
                case 'right':
                    return room.right >= menuWidth + offset + MARGIN;
                default:
                    return false;
            }
        };

        let resolved = placement;
        if (!fits(resolved)) {
            const opposite = OPPOSITE_PLACEMENT[resolved];
            // Place insuffisante des deux côtés : on garde le placement
            // demandé et c'est le clamp final qui Limitera le débordement.
            if (fits(opposite)) resolved = opposite;
        }

        let top: number;
        let left: number;

        switch (resolved) {
            case 'top':
                top = room.top - offset - menuHeight;
                left = triggerRect.left + triggerRect.width / 2 - menuWidth / 2;
                break;
            case 'bottom':
                top = triggerRect.bottom + offset;
                left = triggerRect.left + triggerRect.width / 2 - menuWidth / 2;
                break;
            case 'left':
                top = triggerRect.top + triggerRect.height / 2 - menuHeight / 2;
                left = room.left - offset - menuWidth;
                break;
            default:
                top = triggerRect.top + triggerRect.height / 2 - menuHeight / 2;
                left = triggerRect.right + offset;
                break;
        }

        return {
            coords: {
                top: clamp(top, MARGIN, Math.max(MARGIN, viewportHeight - menuHeight - MARGIN)),
                left: clamp(left, MARGIN, Math.max(MARGIN, viewportWidth - menuWidth - MARGIN)),
            },
            placement: resolved,
        };
    }, [placement, offset]);

    const reposition = useCallback(() => {
        if (!isOpen) return;
        setPosition(computePosition());
    }, [isOpen, computePosition]);

    // Mesure puis placement : `useLayoutEffect` garantit que le menu est
    // dans le DOM (donc mesurable) avant de peindre.
    useLayoutEffect(() => {
        if (!isOpen) {
            setPosition(null);

            return;
        }

        setPosition(computePosition());
    }, [isOpen, computePosition]);

    // Le menu suit son déclencheur : sans cela il restait figé à sa
    // position d'ouverture et finissait sous le bord de la fenêtre dès que
    // la page défilait, que la fenêtre changeait de taille, ou que le
    // sidebar se repliait.
    useEffect(() => {
        if (!isOpen) return;

        window.addEventListener('resize', reposition);
        window.addEventListener('scroll', reposition, true);

        const triggerEl = triggerRef.current;
        const observer = typeof ResizeObserver === 'undefined' || !triggerEl
            ? null
            : new ResizeObserver(reposition);
        if (observer && triggerEl) observer.observe(triggerEl);

        return () => {
            window.removeEventListener('resize', reposition);
            window.removeEventListener('scroll', reposition, true);
            observer?.disconnect();
        };
    }, [isOpen, reposition]);

    const toggle = useCallback(() => {
        setIsOpen((prev) => !prev);
    }, []);

    const close = useCallback(() => setIsOpen(false), []);
    const open = useCallback(() => setIsOpen(true), []);

    useEffect(() => {
        if (!closeOnOutsideClick) return;
        const handleClickOutside = (event: MouseEvent) => {
            if (
                triggerRef.current &&
                !triggerRef.current.contains(event.target as Node) &&
                menuRef.current &&
                !menuRef.current.contains(event.target as Node)
            ) {
                close();
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, [closeOnOutsideClick, close]);

    useEffect(() => {
        if (!closeOnEscape) return;
        const handleEscape = (event: KeyboardEvent) => {
            if (event.key === 'Escape') close();
        };
        if (isOpen) {
            document.addEventListener('keydown', handleEscape);
        }
        return () => document.removeEventListener('keydown', handleEscape);
    }, [closeOnEscape, isOpen, close]);

    const handleItemClick = (item: PopoverMenuItem) => {
        if (item.disabled) return;
        item.onClick?.();
        if (closeOnClickItem) close();
    };

    const classes = {
        trigger: 'popover-menu__trigger',
        // La classe suit le placement RÉELLEMENT retenu (le hook peut
        // retourner le menu du côté opposé) : l'animation part donc du
        // bon côté.
        menu: `popover-menu popover-menu--${position?.placement ?? placement} ${isOpen ? 'popover-menu--open' : ''} ${className}`.trim(),
    };

    return {
        isOpen,
        triggerRef,
        menuRef,
        toggle,
        close,
        open,
        handleItemClick,
        classes,
        placement: position?.placement ?? placement,
        offset,
        coords: position?.coords ?? null,
    };
}
