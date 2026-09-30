import { useState, useMemo } from 'react';

export interface HeaderNavItem {
    id: string;
    label: React.ReactNode;
    href?: string;
    icon?: React.ReactNode;
    active?: boolean;
}

export interface UseHeaderProps {
    logo?: React.ReactNode;
    navItems?: HeaderNavItem[];
    actions?: React.ReactNode;
    position?: 'fixed' | 'sticky' | 'static';
    variant?: 'default' | 'transparent' | 'colored';
    mobileBreakpoint?: number;
    onNavItemClick?: (item: HeaderNavItem) => void;
    className?: string;
}

export function useHeader({
                              logo,
                              navItems = [],
                              actions,
                              position = 'sticky',
                              variant = 'default',
                              mobileBreakpoint = 768,
                              onNavItemClick,
                              className = '',
                          }: UseHeaderProps) {
    const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

    const toggleMobileMenu = () => setIsMobileMenuOpen((prev) => !prev);
    const closeMobileMenu = () => setIsMobileMenuOpen(false);

    const classes = useMemo(() => {
        const base = 'header';
        const positionClass = `header--${position}`;
        const variantClass = `header--${variant}`;
        return [base, positionClass, variantClass, className].filter(Boolean).join(' ');
    }, [position, variant, className]);

    return {
        classes,
        logo,
        navItems,
        actions,
        isMobileMenuOpen,
        toggleMobileMenu,
        closeMobileMenu,
    };
}
