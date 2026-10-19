import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import {useModal, UseModalProps} from "../../../hook-components/UI/Modal";

export interface ModalProps extends UseModalProps {
    title?: React.ReactNode;
    children: React.ReactNode;
    footer?: React.ReactNode;
    expandable?: boolean;
    expandToViewport?: boolean;
    initiallyExpanded?: boolean;
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
                          expandable = false,
                          expandToViewport = false,
                          initiallyExpanded = false,
                      }: ModalProps) {
    const { classes, overlayClick } = useModal({
        isOpen,
        onClose,
        size,
        closeOnOverlayClick,
        closeOnEscape,
        className,
    });
    const [expanded, setExpanded] = useState(false);
    const [centerBounds, setCenterBounds] = useState<React.CSSProperties>();

    useEffect(() => {
        if (!isOpen || !expanded || expandToViewport) {
            setCenterBounds(undefined);
            return;
        }

        const centerPanel = document.querySelector<HTMLElement>('.main-layout__content');
        if (!centerPanel) return;

        const updateBounds = () => {
            const rect = centerPanel.getBoundingClientRect();
            const pageHeader = centerPanel.querySelector<HTMLElement>('.organization-page__header');
            const headerBottom = pageHeader?.getBoundingClientRect().bottom ?? rect.top;
            const top = Math.min(rect.bottom, Math.max(rect.top, headerBottom + 12));
            setCenterBounds({ left: rect.left, top, width: rect.width, height: Math.max(0, rect.bottom - top) });
        };
        centerPanel.scrollTo({ top: 0, behavior: 'auto' });
        updateBounds();
        const observer = new ResizeObserver(updateBounds);
        observer.observe(centerPanel);
        const pageHeader = centerPanel.querySelector<HTMLElement>('.organization-page__header');
        if (pageHeader) observer.observe(pageHeader);
        window.addEventListener('resize', updateBounds);
        return () => {
            observer.disconnect();
            window.removeEventListener('resize', updateBounds);
        };
    }, [isOpen, expanded, expandToViewport]);

    useEffect(() => {
        setExpanded(isOpen && initiallyExpanded);
    }, [isOpen, initiallyExpanded]);

    if (!isOpen) return null;

    return createPortal(
        <div className={`modal__overlay${expanded ? expandToViewport ? ' modal__overlay--viewport-expanded' : ' modal__overlay--center-expanded' : ''}`} style={expanded && !expandToViewport ? centerBounds : undefined} onClick={overlayClick}>
            <div className={`${classes}${expandable ? ' modal--expandable' : ''}${expanded ? expandToViewport ? ' modal--viewport-expanded' : ' modal--center-expanded' : ''}`} role="dialog" aria-modal="true">
                <button className="modal__close" onClick={onClose} aria-label={('Fermer')}>
                    &times;
                </button>
                {title && <div className="modal__header">{title}</div>}
                {expandable && <button className="modal__expand" type="button" onClick={() => setExpanded((value) => !value)} aria-label={expanded ? 'Réduire au format normal' : expandToViewport ? 'Afficher en plein écran' : 'Agrandir au panneau central'} title={expanded ? 'Réduire' : expandToViewport ? 'Plein écran' : 'Agrandir au panneau central'}>{expanded ? '⤢' : '⛶'}</button>}
                <div className="modal__body">{children}</div>
                {footer && <div className="modal__footer">{footer}</div>}
            </div>
        </div>,
        document.body
    );
}
