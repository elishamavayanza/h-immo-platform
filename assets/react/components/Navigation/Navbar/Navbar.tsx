import React from 'react';
import {NavbarItem, useNavbar, UseNavbarProps} from '../../../hook-components/Navigation/Navbar';
import { useI18n } from '@/react/i18n/I18nContext';

const HamburgerIcon = ({ isOpen }: { isOpen: boolean }) => (
    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" strokeWidth="2">
        {isOpen ? (
            <>
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
            </>
        ) : (
            <>
                <line x1="3" y1="6" x2="21" y2="6" />
                <line x1="3" y1="12" x2="21" y2="12" />
                <line x1="3" y1="18" x2="21" y2="18" />
            </>
        )}
    </svg>
);

export interface NavbarProps extends UseNavbarProps {
    logo?: React.ReactNode;
    actions?: React.ReactNode;
    onItemClick?: (item: NavbarItem) => void;
}

export function Navbar({
                           items,
                           logo,
                           actions,
                           position,
                           variant,
                           mobileBreakpoint,
                           className,
                           onItemClick,
                       }: NavbarProps) {
    const { classes, isMobileMenuOpen, toggleMobileMenu, closeMobileMenu } = useNavbar({
        items,
        position,
        variant,
        mobileBreakpoint,
        className,
    });
    const { t } = useI18n();

    const handleItemClick = (item: NavbarItem) => {
        onItemClick?.(item);
        closeMobileMenu();
    };

    return (
        <header className={classes}>
            <div className="navbar__inner">
                {logo && <div className="navbar__brand">{logo}</div>}
                <nav className="navbar__links" aria-label={t('Navigation principale')}>
                    {items.map((item) => (
                        <a
                            key={item.id}
                            href={item.href}
                            className={`navbar__link ${item.active ? 'navbar__link--active' : ''}`}
                            onClick={() => handleItemClick(item)}
                        >
                            {item.icon && <span className="navbar__link-icon">{item.icon}</span>}
                            {item.label}
                        </a>
                    ))}
                </nav>
                {actions && <div className="navbar__actions">{actions}</div>}
                <button
                    className="navbar__toggle"
                    onClick={toggleMobileMenu}
                    aria-label={t('Menu')}
                    aria-expanded={isMobileMenuOpen}
                >
                    <HamburgerIcon isOpen={isMobileMenuOpen} />
                </button>
            </div>

            {isMobileMenuOpen && (
                <div className="navbar__mobile-menu">
                    {items.map((item) => (
                        <a
                            key={item.id}
                            href={item.href}
                            className={`navbar__link ${item.active ? 'navbar__link--active' : ''}`}
                            onClick={() => handleItemClick(item)}
                        >
                            {item.icon && <span className="navbar__link-icon">{item.icon}</span>}
                            {item.label}
                        </a>
                    ))}
                    {actions && <div className="navbar__mobile-actions">{actions}</div>}
                </div>
            )}
        </header>
    );
}
