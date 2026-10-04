import React from 'react';
import { createPortal } from 'react-dom';
import {useDrawer, UseDrawerProps} from "../../../../assets/react/hook-components/UI/Drawer";


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
                {header && <div className="drawer__header">{typeof header === 'string' ? (header) : header}</div>}
                <div className="drawer__body">{typeof children === 'string' ? (children) : children}</div>
                {footer && <div className="drawer__footer">{typeof footer === 'string' ? (footer) : footer}</div>}
            </div>
        </div>,
        document.body
    );
}
