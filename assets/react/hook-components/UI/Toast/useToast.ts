import { useState, useCallback } from 'react';

export type ToastVariant = 'info' | 'success' | 'warning' | 'error';
export type ToastPosition = 'top-right' | 'top-left' | 'bottom-right' | 'bottom-left';

export interface ToastItem {
    id: number;
    message: string;
    variant: ToastVariant;
    duration?: number;
}

export interface UseToastOptions {
    defaultDuration?: number;
    position?: ToastPosition;
}

export function useToast({
                             defaultDuration = 5000,
                             position = 'top-right',
                         }: UseToastOptions = {}) {
    const [toasts, setToasts] = useState<ToastItem[]>([]);

    const removeToast = useCallback((id: number) => {
        setToasts((prev) => prev.filter((toast) => toast.id !== id));
    }, []);

    const addToast = useCallback(
        (message: string, variant: ToastVariant = 'info', duration?: number) => {
            const id = Date.now() + Math.random();
            const newToast: ToastItem = { id, message, variant, duration: duration ?? defaultDuration };
            setToasts((prev) => [...prev, newToast]);
            if (newToast.duration && newToast.duration > 0) {
                setTimeout(() => removeToast(id), newToast.duration);
            }
        },
        [defaultDuration, removeToast]
    );

    return {
        toasts,
        addToast,
        removeToast,
        position,
    };
}
