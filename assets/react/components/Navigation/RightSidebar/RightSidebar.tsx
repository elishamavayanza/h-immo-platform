import React, { useState, useRef, useCallback, useEffect } from 'react';
import { useRightSidebar, UseRightSidebarProps } from '../../../hook-components/Navigation/RightSidebar';
import { useIsCompact } from '@/react/hooks/useIsCompact';   // ← mobile / tablette / portrait
import { useI18n } from '@/react/i18n/I18nContext';

const CollapseIcon = () => (
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2">
        <polyline points="15 18 9 12 15 6" />
    </svg>
);

const ExpandIcon = () => (
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2">
        <polyline points="9 18 15 12 9 6" />
    </svg>
);

const CloseIcon = () => (
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
        <line x1="18" y1="6" x2="6" y2="18" />
        <line x1="6" y1="6" x2="18" y2="18" />
    </svg>
);

export interface RightSidebarProps extends UseRightSidebarProps {
    children: React.ReactNode;
    title?: React.ReactNode;
    header?: React.ReactNode;
    footer?: React.ReactNode;
    onToggle?: (collapsed: boolean) => void;
    minWidth?: number;
    maxWidth?: number;
    onResize?: (width: number) => void;
    closeThreshold?: number;
    collapsedWidth?: number;
}

export function RightSidebar({
                                 children,
                                 title,
                                 header,
                                 footer,
                                 variant,
                                 size,
                                 collapsible = true,
                                 defaultCollapsed = false,
                                 className,
                                 onToggle,
                                 minWidth = 200,
                                 maxWidth = 600,
                                 onResize,
                                 closeThreshold = 60,
                                 collapsedWidth = 35,
                             }: RightSidebarProps) {
    const isCompact = useIsCompact();
    const { t } = useI18n();

    // Ajustements mode compact (mobile / tablette / portrait)
    const effectiveMinWidth = isCompact ? 120 : minWidth;
    const effectiveMaxWidth = isCompact ? 280 : maxWidth;
    const effectiveCloseThreshold = isCompact ? 40 : closeThreshold;
    const effectiveCollapsedWidth = isCompact ? 25 : collapsedWidth;

    // Largeur initiale adaptée
    const initialWidth = isCompact
        ? 260
        : size === 'small' ? 200 : size === 'large' ? 340 : 280;

    const { classes } = useRightSidebar({ variant, size, collapsible, defaultCollapsed, className });

    const [width, setWidth] = useState<number>(initialWidth);
    const [isFullyCollapsed, setIsFullyCollapsed] = useState(
        defaultCollapsed === undefined ? isCompact : defaultCollapsed
    );
    const asideRef = useRef<HTMLElement>(null);

    const isDraggingRef = useRef(false);
    const rafRef = useRef<number | null>(null);
    const isFullyCollapsedRef = useRef(isFullyCollapsed);

    useEffect(() => {
        isFullyCollapsedRef.current = isFullyCollapsed;
    }, [isFullyCollapsed]);

    const updateWidthDOM = useCallback((newWidth: number) => {
        if (asideRef.current) {
            asideRef.current.style.width = `${newWidth}px`;
        }
    }, []);

    const handleMouseMove = useCallback((e: MouseEvent) => {
        if (!isDraggingRef.current) return;

        if (rafRef.current !== null) cancelAnimationFrame(rafRef.current);

        rafRef.current = requestAnimationFrame(() => {
            let newWidth = window.innerWidth - e.clientX;

            if (isFullyCollapsedRef.current) {
                if (newWidth > effectiveCollapsedWidth + 20) {
                    setIsFullyCollapsed(false);
                    isFullyCollapsedRef.current = false;
                } else {
                    return;
                }
            }

            if (newWidth < effectiveCloseThreshold) {
                setIsFullyCollapsed(true);
                isFullyCollapsedRef.current = true;
                updateWidthDOM(effectiveCollapsedWidth);
                return;
            }

            newWidth = Math.min(effectiveMaxWidth, Math.max(effectiveMinWidth, newWidth));
            updateWidthDOM(newWidth);
        });
    }, [effectiveMinWidth, effectiveMaxWidth, effectiveCloseThreshold, effectiveCollapsedWidth, updateWidthDOM]);

    const handleMouseUp = useCallback(() => {
        isDraggingRef.current = false;
        document.body.classList.remove('right-sidebar-resizing');
        document.removeEventListener('mousemove', handleMouseMove);
        document.removeEventListener('mouseup', handleMouseUp);

        if (rafRef.current !== null) {
            cancelAnimationFrame(rafRef.current);
            rafRef.current = null;
        }

        if (asideRef.current) {
            const currentWidth = parseInt(asideRef.current.style.width, 10);
            if (!isFullyCollapsedRef.current && !isNaN(currentWidth)) {
                setWidth(currentWidth);
                onResize?.(currentWidth);
            } else {
                setWidth(effectiveCollapsedWidth);
                onResize?.(effectiveCollapsedWidth);
            }
        }
    }, [handleMouseMove, effectiveCollapsedWidth, onResize]);

    const startDragging = useCallback((e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        isDraggingRef.current = true;
        document.body.classList.add('right-sidebar-resizing');
        document.addEventListener('mousemove', handleMouseMove);
        document.addEventListener('mouseup', handleMouseUp);
    }, [handleMouseMove, handleMouseUp]);

    useEffect(() => {
        return () => {
            document.body.classList.remove('right-sidebar-resizing');
            document.removeEventListener('mousemove', handleMouseMove);
            document.removeEventListener('mouseup', handleMouseUp);
            if (rafRef.current !== null) cancelAnimationFrame(rafRef.current);
        };
    }, [handleMouseMove, handleMouseUp]);

    const handleToggle = () => {
        const nextState = !isFullyCollapsed;
        setIsFullyCollapsed(nextState);
        const targetWidth = nextState ? effectiveCollapsedWidth : width;
        updateWidthDOM(targetWidth);
        onToggle?.(nextState);
    };

    const sidebarStyle: React.CSSProperties = {
        width: isFullyCollapsed ? `${effectiveCollapsedWidth}px` : `${width}px`,
        transition: isDraggingRef.current
            ? 'none'
            : `width 0.3s ease${isCompact ? ', transform 0.32s cubic-bezier(0.4, 0, 0.2, 1)' : ''}`,
        transform: isCompact && isFullyCollapsed ? 'translateX(105%)' : 'none',
    };

    useEffect(() => {
        updateWidthDOM(isFullyCollapsed ? effectiveCollapsedWidth : width);
    }, [isFullyCollapsed, width, effectiveCollapsedWidth, updateWidthDOM]);

    return (
        <>
            {/* Backdrop : tap à l'extérieur → ferme le drawer */}
            {isCompact && !isFullyCollapsed && (
                <div
                    className="right-sidebar__backdrop"
                    onClick={handleToggle}
                    aria-hidden="true"
                />
            )}

            {/* Bouton flottant (rond / chip) pour rouvrir le panneau fermé */}
            {isCompact && isFullyCollapsed && (
                <button
                    type="button"
                    className="right-sidebar__mobile-trigger"
                    onClick={handleToggle}
                    aria-label={t('Ouvrir le panneau')}
                    title={t('Ouvrir le panneau')}
                >
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="2">
                        <polyline points="9 18 15 12 9 6" />
                    </svg>
                    {title && <span className="right-sidebar__mobile-trigger-label">{title}</span>}
                </button>
            )}

            <aside
                ref={asideRef}
                className={`${classes} ${isFullyCollapsed ? 'right-sidebar--fully-collapsed' : ''}`}
                style={sidebarStyle}
            >
                {isFullyCollapsed ? (
                    <div
                        className="right-sidebar__collapsed-strip"
                        onMouseDown={startDragging}
                        onClick={(e) => {
                            if (!isDraggingRef.current) handleToggle();
                        }}
                        title={t('Cliquer pour ouvrir ou glisser vers la gauche')}
                    >
                        <span className="right-sidebar__collapsed-text">{title || ''}</span>
                    </div>
                ) : (
                    <>
                        <div
                            className="right-sidebar__resizer"
                            onMouseDown={startDragging}
                            title={t('Glisser pour redimensionner')}
                        />

                        {header && <div className="right-sidebar__header">{header}</div>}
                        {title && <div className="right-sidebar__title">{title}</div>}
                        {isCompact && (
                            <button
                                type="button"
                                className="right-sidebar__collapse"
                                onClick={handleToggle}
                                aria-label={t('Fermer le panneau')}
                                title={t('Fermer le panneau')}
                            >
                                <CloseIcon />
                            </button>
                        )}
                        <div className="right-sidebar__content">{children}</div>
                        {footer && <div className="right-sidebar__footer">{footer}</div>}
                    </>
                )}
            </aside>
        </>
    );
}
