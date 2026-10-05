import { useState, useRef, useEffect, useCallback } from 'react';
import { useFloatingPosition, type FloatingPlacement } from './useFloatingPosition';

export type PopoverPlacement = FloatingPlacement;

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

    const triggerRef = useRef<HTMLDivElement>(null);

    // `getAnchorEl` doit être stable : s'il change à chaque rendu,
    // `useFloatingPosition` recalcule la position en boucle (effet → setState → rendu).
    const getAnchorEl = useCallback(() => triggerRef.current, []);

    // La géométrie est déléguée à `useFloatingPosition`, partagé avec le
    // flyout de sous-menu du sidebar : un seul moteur de placement pour tous
    // les panneaux flottants de l'application.
    const {
        floatingRef: menuRef,
        coords,
        resolvedPlacement,
        isPositioned,
    } = useFloatingPosition<HTMLDivElement>({
        getAnchorEl,
        placement,
        offset,
        isOpen,
    });

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
    }, [closeOnOutsideClick, close, menuRef]);

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
        menu: `popover-menu popover-menu--${resolvedPlacement} ${isOpen ? 'popover-menu--open' : ''} ${className}`.trim(),
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
        placement: resolvedPlacement,
        offset,
        coords,
        isPositioned,
    };
}
