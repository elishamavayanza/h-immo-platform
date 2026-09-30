import React from 'react';
import { createPortal } from 'react-dom';
import { useModal, UseModalProps } from '@/react/hook-components/UI/Modal';
import { useI18n } from '@/react/i18n/I18nContext';

export interface ModalProps extends UseModalProps {
    title?: React.ReactNode;
    children: React.ReactNode;
    footer?: React.ReactNode;
}

export function Modal({
                          isOpen,
                          onClose,
                          size = 'medium',
                          closeOnOverlayClick = true,
                          closeOnEscape = true,
                          className,
                          title,
                          children,
                          footer,
                      }: ModalProps) {
    const { t } = useI18n();
    const { classes, overlayClick } = useModal({
        isOpen,
        onClose,
        size,
        closeOnOverlayClick,
        closeOnEscape,
        className,
    });

    if (!isOpen) return null;

    return createPortal(
        <div className="modal__overlay" onClick={overlayClick}>
            <div className={classes} role="dialog" aria-modal="true">
                <button className="modal__close" onClick={onClose} aria-label={t('Fermer')}>
                    &times;
                </button>
                {title && <div className="modal__header">{title}</div>}
                <div className="modal__body">{children}</div>
                {footer && <div className="modal__footer">{footer}</div>}
            </div>
        </div>,
        document.body
    );
}
