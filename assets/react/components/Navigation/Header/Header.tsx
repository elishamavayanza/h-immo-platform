import React from 'react';
import { useHeader, UseHeaderProps, HeaderNavItem } from '../../../hook-components/Navigation/Header';
import { useI18n } from '@/react/i18n/I18nContext';

const MenuIcon = ({ open }: { open: boolean }) => (
    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" strokeWidth="2">
        {open ? (
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

export interface HeaderProps extends UseHeaderProps {}

export function Header({
                           logo,
                           navItems = [],               // valeur par défaut
                           actions,
                           position,
                           variant,
                           mobileBreakpoint,
                           onNavItemClick,
                           className,
                        }: HeaderProps) {
    const {
        classes,
        isMobileMenuOpen,
        toggleMobileMenu,
        closeMobileMenu,
    } = useHeader({
        logo,
        navItems,
        actions,
        position,
        variant,
        mobileBreakpoint,
        onNavItemClick,
        className,
    });
    const { t } = useI18n();

    const handleNavItemClick = (item: HeaderNavItem) => {
        onNavItemClick?.(item);
        closeMobileMenu();
    };

    return (
        <header className={classes}>
            <div className="header__inner">
                {logo && <div className="header__logo">{logo}</div>}
                <nav className="header__nav" aria-label={t('Navigation principale')}>
                    {navItems.map((item) => (
                        <a
                            key={item.id}
                            href={item.href}
                            className={`header__nav-link ${item.active ? 'header__nav-link--active' : ''}`}
                            onClick={() => handleNavItemClick(item)}
                        >
                            {item.icon && <span className="header__nav-icon">{item.icon}</span>}
                            {item.label}
                        </a>
                    ))}
                </nav>
                {actions && <div className="header__actions">{actions}</div>}
                <button
                    className="header__toggle"
                    onClick={toggleMobileMenu}
                    aria-label={t('Menu')}
                    aria-expanded={isMobileMenuOpen}
                >
                    <MenuIcon open={isMobileMenuOpen} />
                </button>
            </div>

            {isMobileMenuOpen && (
                <div className="header__mobile-menu">
                    {navItems.map((item) => (
                        <a
                            key={item.id}
                            href={item.href}
                            className={`header__nav-link ${item.active ? 'header__nav-link--active' : ''}`}
                            onClick={() => handleNavItemClick(item)}
                        >
                            {item.icon && <span className="header__nav-icon">{item.icon}</span>}
                            {item.label}
                        </a>
                    ))}
                    {actions && <div className="header__mobile-actions">{actions}</div>}
                </div>
            )}
        </header>
    );
}
