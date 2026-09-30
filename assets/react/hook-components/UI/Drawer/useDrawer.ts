import { useEffect, useMemo } from 'react';

export type DrawerPosition = 'left' | 'right' | 'top' | 'bottom';
export type DrawerSize = 'small' | 'medium' | 'large' | 'full';

export interface UseDrawerProps {
    isOpen: boolean;
    onClose: () => void;
    position?: DrawerPosition;
    size?: DrawerSize;
    closeOnOverlayClick?: boolean;
    closeOnEscape?: boolean;
    className?: string;
}

export function useDrawer({
                              isOpen,
                              onClose,
                              position = 'right',
                              size = 'medium',
                              closeOnOverlayClick = true,
                              closeOnEscape = true,
                              className = '',
                          }: UseDrawerProps) {
    useEffect(() => {
        if (!isOpen) return;
        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape' && closeOnEscape) {
                onClose();
            }
        };
        document.addEventListener('keydown', handleKeyDown);
        document.body.style.overflow = 'hidden';
        return () => {
            document.removeEventListener('keydown', handleKeyDown);
            document.body.style.overflow = '';
        };
    }, [isOpen, closeOnEscape, onClose]);

    const classes = useMemo(() => {
        const base = 'drawer';
        const positionClass = `drawer--${position}`;
        const sizeClass = `drawer--${size}`;
        const openClass = isOpen ? 'drawer--open' : '';
        return [base, positionClass, sizeClass, openClass, className].filter(Boolean).join(' ');
    }, [position, size, isOpen, className]);

    const overlayClick = (e: React.MouseEvent<HTMLDivElement>) => {
        if (e.target === e.currentTarget && closeOnOverlayClick) {
            onClose();
        }
    };

    return { classes, overlayClick };
}
