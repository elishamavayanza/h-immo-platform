import React from 'react';
import { ToastItem, ToastPosition } from '../../../hook-components/UI/Toast';
import { useI18n } from '@/react/i18n/I18nContext';

// Icônes SVG inline (pas d'emoji)
const IconInfo = () => (
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="2">
        <circle cx="12" cy="12" r="10" />
        <line x1="12" y1="16" x2="12" y2="12" />
        <line x1="12" y1="8" x2="12.01" y2="8" />
    </svg>
);

const IconSuccess = () => (
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="2">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
        <polyline points="22 4 12 14.01 9 11.01" />
    </svg>
);

const IconWarning = () => (
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="2">
        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
        <line x1="12" y1="9" x2="12" y2="13" />
        <line x1="12" y1="17" x2="12.01" y2="17" />
    </svg>
);

const IconError = () => (
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="2">
        <circle cx="12" cy="12" r="10" />
        <line x1="15" y1="9" x2="9" y2="15" />
        <line x1="9" y1="9" x2="15" y2="15" />
    </svg>
);

const IconClose = () => (
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2">
        <line x1="18" y1="6" x2="6" y2="18" />
        <line x1="6" y1="6" x2="18" y2="18" />
    </svg>
);

export interface ToastContainerProps {
    toasts: ToastItem[];
    position?: ToastPosition;
    onClose: (id: number) => void;
}

const variantIcon = {
    info: <IconInfo />,
    success: <IconSuccess />,
    warning: <IconWarning />,
    error: <IconError />,
};

export function ToastContainer({ toasts, position = 'top-right', onClose }: ToastContainerProps) {
    const { t } = useI18n();
    return (
        <div className={`toast-container toast-container--${position}`}>
            {toasts.map((toast) => (
                <div key={toast.id} className={`toast toast--${toast.variant}`} role="alert">
                    <div className="toast__icon" aria-hidden="true">
                        {variantIcon[toast.variant]}
                    </div>
                    <div className="toast__message">{toast.message}</div>
                    <button className="toast__close" onClick={() => onClose(toast.id)} aria-label={t('Fermer la notification')}>
                        <IconClose />
                    </button>
                </div>
            ))}
        </div>
    );
}
