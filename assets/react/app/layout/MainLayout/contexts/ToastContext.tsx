// ============================================================
// assets/react/app/layout/MainLayout/contexts/ToastContext.tsx
// Notifications toast du back-office.
//
// Contexte minimal : `push(type, message)` ajoute une notification,
// `dismiss(id)` la retire. Les toasts disparaissent automatiquement
// après TOAST_DURATION_MS ; la pile est rendue par le provider lui-même
// (viewport) pour éviter qu'un layout oublie d'en dessiner une.
// ============================================================

import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';

export interface ToastMessage {
    id: number;
    type: 'success' | 'error' | 'info' | 'warning';
    message: string;
}

export interface ToastContextValue {
    toasts: ToastMessage[];
    push: (type: ToastMessage['type'], message: string) => void;
    dismiss: (id: number) => void;
}

const TOAST_DURATION_MS = 5000;

const ToastContext = createContext<ToastContextValue | undefined>(undefined);

export function ToastProvider({ children }: { children: ReactNode }) {
    const [toasts, setToasts] = useState<ToastMessage[]>([]);
    const nextId = useRef(1);

    const dismiss = useCallback((id: number) => {
        setToasts(prev => prev.filter(t => t.id !== id));
    }, []);

    const push = useCallback((type: ToastMessage['type'], message: string) => {
        const id = nextId.current++;
        setToasts(prev => [...prev, { id, type, message }]);
    }, []);

    // Expiration automatique : un toast ne reste jamais plus longtemps que
    // TOAST_DURATION_MS, même si personne ne le ferme.
    useEffect(() => {
        if (toasts.length === 0) return;

        const timer = window.setTimeout(() => {
            setToasts(prev => prev.slice(1));
        }, TOAST_DURATION_MS);

        return () => window.clearTimeout(timer);
    }, [toasts]);

    const value = useMemo<ToastContextValue>(() => ({ toasts, push, dismiss }), [toasts, push, dismiss]);

    return (
        <ToastContext.Provider value={value}>
            {children}
            {toasts.length > 0 && (
                <div className="toast-container toast-container--top-right" role="region" aria-live="polite" aria-label="Notifications">
                    {toasts.map(toast => (
                        <div key={toast.id} className={`toast toast--${toast.type}`} role="status">
                            <span className="toast__message">{toast.message}</span>
                            <button
                                type="button"
                                className="toast__close"
                                aria-label="Fermer la notification"
                                onClick={() => dismiss(toast.id)}
                            >
                                ✕
                            </button>
                        </div>
                    ))}
                </div>
            )}
        </ToastContext.Provider>
    );
}

export function useToast(): ToastContextValue {
    const context = useContext(ToastContext);
    if (!context) {
        throw new Error('useToast doit être utilisé dans un <ToastProvider>.');
    }
    return context;
}