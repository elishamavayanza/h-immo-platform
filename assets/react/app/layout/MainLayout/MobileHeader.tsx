// ============================================================
// assets/react/app/layout/MainLayout/MobileHeader.tsx
// En-tête mobile avec bouton hamburger pour ouvrir le sidebar.
// Visible UNIQUEMENT sur mobile (< 768px).
// ============================================================

import React from 'react';

export interface MobileHeaderProps {
    /** Ouvre le sidebar mobile. */
    onOpenSidebar: () => void;
    /** Indique si le sidebar est ouvert (pour aria-expanded). */
    isSidebarOpen: boolean;
    /** Titre affiché à côté du hamburger. */
    title?: string;
    /** Nom de l'organisation active (optionnel). */
    organizationName?: string;
}

export function MobileHeader({
                                 onOpenSidebar,
                                 isSidebarOpen,
                                 title = 'H-Immo',
                                 organizationName,
                             }: MobileHeaderProps) {
    return (
        <header className="mobile-header" role="banner">
            <button
                type="button"
                className="mobile-header__hamburger"
                onClick={onOpenSidebar}
                aria-expanded={isSidebarOpen}
                aria-controls="main-sidebar"
                aria-label={isSidebarOpen ? 'Fermer le menu' : 'Ouvrir le menu'}
            >
                <span className="mobile-header__hamburger-box" aria-hidden="true">
                    <span className="mobile-header__hamburger-line" />
                    <span className="mobile-header__hamburger-line" />
                    <span className="mobile-header__hamburger-line" />
                </span>
            </button>
            <div className="mobile-header__title">
                <span className="mobile-header__brand">{title}</span>
                {organizationName && (
                    <span className="mobile-header__org">{organizationName}</span>
                )}
            </div>
            <div className="mobile-header__spacer" />
        </header>
    );
}