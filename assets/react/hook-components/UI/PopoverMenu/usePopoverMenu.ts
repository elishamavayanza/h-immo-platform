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
    const [coords, setCoords] = useState({ top: 0, left: 0 });
    const triggerRef = useRef<HTMLDivElement>(null);
    const menuRef = useRef<HTMLDivElement>(null);

    // Position de base selon le placement
    const getBasePosition = useCallback(
        (triggerRect: DOMRect) => {
            switch (placement) {
                case 'bottom':
                    return { top: triggerRect.bottom + offset, left: triggerRect.left + triggerRect.width / 2 };
                case 'top':
                    return { top: triggerRect.top - offset, left: triggerRect.left + triggerRect.width / 2 };
                case 'left':
                    return { top: triggerRect.top + triggerRect.height / 2, left: triggerRect.left - offset };
                case 'right':
                    return { top: triggerRect.top + triggerRect.height / 2, left: triggerRect.right + offset };
                default:
                    return { top: triggerRect.bottom + offset, left: triggerRect.left + triggerRect.width / 2 };
            }
        },
        [placement, offset]
    );

    // Ajuste la position pour rester dans le viewport
    const clampToViewport = useCallback(
        (base: { top: number; left: number }) => {
            const menuEl = menuRef.current;
            if (!menuEl) return base;

            const menuWidth = menuEl.offsetWidth;
            const menuHeight = menuEl.offsetHeight;
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;

            let { top, left } = base;

            // Ajustement vertical selon le placement
            switch (placement) {
                case 'bottom':
                    if (top + menuHeight > viewportHeight - MARGIN) {
                        top = viewportHeight - menuHeight - MARGIN;
                    }
                    break;
                case 'top':
                    if (top - menuHeight < MARGIN) {
                        top = menuHeight + MARGIN; // repousse vers le bas
                    }
                    break;
                case 'left':
                case 'right':
                    if (top - menuHeight / 2 < MARGIN) {
                        top = MARGIN + menuHeight / 2;
                    }
                    if (top + menuHeight / 2 > viewportHeight - MARGIN) {
                        top = viewportHeight - MARGIN - menuHeight / 2;
                    }
                    break;
            }

            // Ajustement horizontal
            if (placement === 'left' || placement === 'right') {
                if (left - menuWidth < MARGIN) {
                    left = MARGIN + menuWidth;
                }
                if (left + menuWidth > viewportWidth - MARGIN) {
                    left = viewportWidth - MARGIN - menuWidth;
                }
            } else {
                // Centré horizontalement (bottom/top)
                if (left - menuWidth / 2 < MARGIN) {
                    left = MARGIN + menuWidth / 2;
                }
                if (left + menuWidth / 2 > viewportWidth - MARGIN) {
                    left = viewportWidth - MARGIN - menuWidth / 2;
                }
            }

            return { top, left };
        },
        [placement]
    );

    const updateCoords = useCallback(() => {
        const triggerRect = triggerRef.current?.getBoundingClientRect();
        if (!triggerRect) return;
        const base = getBasePosition(triggerRect);
        setCoords(base);
    }, [getBasePosition]);

    // Après ouverture et rendu, ajuste la position selon les dimensions réelles du menu
    useLayoutEffect(() => {
        if (!isOpen) return;
        const triggerRect = triggerRef.current?.getBoundingClientRect();
        if (!triggerRect) return;

        const base = getBasePosition(triggerRect);
        const adjusted = clampToViewport(base);
        setCoords(adjusted);
    }, [isOpen, getBasePosition, clampToViewport]);

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
        menu: `popover-menu popover-menu--${placement} ${isOpen ? 'popover-menu--open' : ''} ${className}`.trim(),
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
        placement,
        offset,
        coords,
    };
}
