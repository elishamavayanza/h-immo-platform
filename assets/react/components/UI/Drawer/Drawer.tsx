import React from 'react';
import { createPortal } from 'react-dom';
import { useDrawer, UseDrawerProps } from '@/react/hook-components/UI/Drawer';
import { useI18n } from '@/react/i18n/I18nContext';

export interface DrawerProps extends UseDrawerProps {
    children: React.ReactNode;
    header?: React.ReactNode;
    footer?: React.ReactNode;
}

export function Drawer({
                           isOpen,
                           onClose,
                           position,
                           size,
                           closeOnOverlayClick,
                           closeOnEscape,
                           className,
                           children,
                           header,
                           footer,
                       }: DrawerProps) {
    const { t } = useI18n();
    const { classes, overlayClick } = useDrawer({
        isOpen,
        onClose,
        position,
        size,
        closeOnOverlayClick,
        closeOnEscape,
        className,
    });

    if (!isOpen) return null;

    return createPortal(
        <div className="drawer__overlay" onClick={overlayClick}>
            <div className={classes} role="dialog" aria-modal="true">
                {header && <div className="drawer__header">{typeof header === 'string' ? t(header) : header}</div>}
                <div className="drawer__body">{typeof children === 'string' ? t(children) : children}</div>
                {footer && <div className="drawer__footer">{typeof footer === 'string' ? t(footer) : footer}</div>}
            </div>
        </div>,
        document.body
    );
}
