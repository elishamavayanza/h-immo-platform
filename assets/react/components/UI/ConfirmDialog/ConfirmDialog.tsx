import React from 'react';
import { createPortal } from 'react-dom';
import { useConfirmDialog, UseConfirmDialogProps } from '@/react/hook-components/UI/ConfirmDialog';
import { useI18n } from '@/react/i18n/I18nContext';

export interface ConfirmDialogProps extends UseConfirmDialogProps {}

export function ConfirmDialog({
                                  isOpen,
                                  onClose,
                                  onConfirm,
                                  onCancel,
                                  title,
                                  message,
                                  confirmLabel,
                                  cancelLabel,
                                  size,
                                  className,
                              }: ConfirmDialogProps) {
    const {
        classes,
        handleConfirm,
        handleCancel,
        confirmLabel: confirmText,
        cancelLabel: cancelText,
    } = useConfirmDialog({
        isOpen,
        onClose,
        onConfirm,
        onCancel,
        title,
        message,
        confirmLabel,
        cancelLabel,
        size,
        className,
    });

    const { t } = useI18n();

    if (!isOpen) return null;

    return createPortal(
        <div className="confirm-dialog__overlay" onClick={onClose}>
            <div
                className={classes}
                role="alertdialog"
                aria-modal="true"
                onClick={(e) => e.stopPropagation()}
            >
                {title && <div className="confirm-dialog__title">{typeof title === 'string' ? t(title) : title}</div>}
                <div className="confirm-dialog__message">{typeof message === 'string' ? t(message) : message}</div>
                <div className="confirm-dialog__actions">
                    <button className="confirm-dialog__button confirm-dialog__button--cancel" onClick={handleCancel}>
                        {cancelText}
                    </button>
                    <button className="confirm-dialog__button confirm-dialog__button--confirm" onClick={handleConfirm}>
                        {confirmText}
                    </button>
                </div>
            </div>
        </div>,
        document.body
    );
}
