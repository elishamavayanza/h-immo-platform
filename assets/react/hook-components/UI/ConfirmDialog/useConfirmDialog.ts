import { useEffect, useMemo } from 'react';

export interface UseConfirmDialogProps {
    isOpen: boolean;
    onClose: () => void;
    onConfirm?: () => void;
    onCancel?: () => void;
    title?: React.ReactNode;
    message?: React.ReactNode;
    confirmLabel?: string;
    cancelLabel?: string;
    size?: 'small' | 'medium' | 'large';
    className?: string;
}

export function useConfirmDialog({
                                     isOpen,
                                     onClose,
                                     onConfirm,
                                     onCancel,
                                     title,
                                     message,
                                     confirmLabel = 'Confirmer',
                                     cancelLabel = 'Annuler',
                                     size = 'medium',
                                     className = '',
                                 }: UseConfirmDialogProps) {
    useEffect(() => {
        if (!isOpen) return;
        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                onClose();
            }
        };
        document.addEventListener('keydown', handleKeyDown);
        document.body.style.overflow = 'hidden';
        return () => {
            document.removeEventListener('keydown', handleKeyDown);
            document.body.style.overflow = '';
        };
    }, [isOpen, onClose]);

    const classes = useMemo(() => {
        const base = 'confirm-dialog';
        const sizeClass = `confirm-dialog--${size}`;
        return [base, sizeClass, className].filter(Boolean).join(' ');
    }, [size, className]);

    const handleConfirm = () => {
        onConfirm?.();
        onClose();
    };

    const handleCancel = () => {
        onCancel?.();
        onClose();
    };

    return {
        classes,
        handleConfirm,
        handleCancel,
        confirmLabel,
        cancelLabel,
        title,
        message,
    };
}
