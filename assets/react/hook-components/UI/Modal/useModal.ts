import { useState, useEffect, useMemo } from 'react';

export type ModalSize = 'small' | 'medium' | 'large' | 'fullscreen';

export interface UseModalProps {
    isOpen: boolean;
    onClose: () => void;
    size?: ModalSize;
    closeOnOverlayClick?: boolean;
    closeOnEscape?: boolean;
    className?: string;
}

export function useModal({
                             isOpen,
                             onClose,
                             size = 'medium',
                             closeOnOverlayClick = true,
                             closeOnEscape = true,
                             className = '',
                         }: UseModalProps) {
    const classes = useMemo(() => {
        const base = 'modal';
        const sizeClass = `modal--${size}`;
        return [base, sizeClass, className].filter(Boolean).join(' ');
    }, [size, className]);

    useEffect(() => {
        if (!isOpen) return;

        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape' && closeOnEscape) {
                onClose();
            }
        };

        document.addEventListener('keydown', handleKeyDown);
        document.body.style.overflow = 'hidden'; // bloque le scroll en arrière-plan

        return () => {
            document.removeEventListener('keydown', handleKeyDown);
            document.body.style.overflow = '';
        };
    }, [isOpen, closeOnEscape, onClose]);

    const overlayClick = (e: React.MouseEvent<HTMLDivElement>) => {
        if (e.target === e.currentTarget && closeOnOverlayClick) {
            onClose();
        }
    };

    return { classes, overlayClick };
}
